<?php

declare(strict_types=1);

/*
 * This file is part of a FuelApp project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Receipt\UI\Web\Controller;

use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationHandler;
use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\UI\Api\Resource\Input\ReceiptInput;
use App\Receipt\UI\Api\Resource\Input\ReceiptLineInput;
use App\Receipt\UI\Realtime\ReceiptStreamPublisher;
use App\Station\Application\Repository\StationRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use UnexpectedValueException;

final class CreateReceiptController extends AbstractController
{
    public function __construct(
        private readonly CreateReceiptWithStationHandler $createReceiptWithStationHandler,
        private readonly StationRepository $stationRepository,
        private readonly ReceiptStreamPublisher $streamPublisher,
        private readonly ValidatorInterface $validator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/receipts/new', name: 'ui_receipt_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $isTurboFrameRequest = $request->headers->has('Turbo-Frame');
        $formData = $this->defaultFormData();
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData = $this->extractFormData($request);
            if (!$this->isCsrfTokenValid('receipt_new', $formData['_token'])) {
                $errors[] = 'Jeton CSRF invalide.';
            } else {
                $errors = $this->validateFormData($formData);
                if ([] === $errors) {
                    $this->persistReceiptFromForm($formData);
                    $this->addFlash('success', 'Receipt created.');

                    return new RedirectResponse($this->generateUrl('ui_receipt_index'), Response::HTTP_SEE_OTHER);
                }
            }
        }

        $response = $this->render($isTurboFrameRequest ? 'receipt/_form.html.twig' : 'receipt/new.html.twig', [
            'formData' => $formData,
            'errors' => $errors,
            'fuelTypes' => array_map(static fn (FuelType $fuelType): string => $fuelType->value, FuelType::cases()),
            'csrfToken' => $this->csrfTokenManager->getToken('receipt_new')->getValue(),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /** @return array<string, string> */
    private function defaultFormData(): array
    {
        return [
            'issuedAt' => new DateTimeImmutable()->format('Y-m-d\TH:i'),
            'fuelType' => FuelType::DIESEL->value,
            'quantityMilliLiters' => '',
            'unitPriceDeciCentsPerLiter' => '',
            'vatRatePercent' => '20',
            'stationName' => '',
            'stationStreetName' => '',
            'stationPostalCode' => '',
            'stationCity' => '',
            'latitudeMicroDegrees' => '',
            'longitudeMicroDegrees' => '',
            '_token' => '',
        ];
    }

    /** @return array<string, string> */
    private function extractFormData(Request $request): array
    {
        $data = $this->defaultFormData();

        foreach (array_keys($data) as $key) {
            $value = $request->request->get($key, '');
            $data[$key] = is_scalar($value) ? (string) $value : '';
        }

        return $data;
    }

    /** @param array<string, string> $formData
     * @return list<string>
     */
    private function validateFormData(array $formData): array
    {
        $issuedAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $formData['issuedAt']) ?: null;

        $lineInput = new ReceiptLineInput(
            $this->nullIfEmpty($formData['fuelType']),
            $this->toNullableInt($formData['quantityMilliLiters']),
            $this->toNullableInt($formData['unitPriceDeciCentsPerLiter']),
            $this->toNullableInt($formData['vatRatePercent']),
        );

        $receiptInput = new ReceiptInput(
            $issuedAt,
            [$lineInput],
            $this->nullIfEmpty($formData['stationName']),
            $this->nullIfEmpty($formData['stationStreetName']),
            $this->nullIfEmpty($formData['stationPostalCode']),
            $this->nullIfEmpty($formData['stationCity']),
            $this->toNullableInt($formData['latitudeMicroDegrees']),
            $this->toNullableInt($formData['longitudeMicroDegrees']),
        );

        $errors = [];
        if (null === $issuedAt) {
            $errors[] = 'Invalid issue date.';
        }

        foreach ($this->validator->validate($receiptInput) as $violation) {
            $errors[] = (string) $violation->getMessage();
        }

        return array_values(array_unique($errors));
    }

    /** @param array<string, string> $formData */
    private function persistReceiptFromForm(array $formData): void
    {
        $line = new CreateReceiptLineCommand(
            FuelType::from($formData['fuelType']),
            $this->toRequiredInt($formData['quantityMilliLiters'], 'quantityMilliLiters'),
            $this->toRequiredInt($formData['unitPriceDeciCentsPerLiter'], 'unitPriceDeciCentsPerLiter'),
            $this->toRequiredInt($formData['vatRatePercent'], 'vatRatePercent'),
        );

        $command = new CreateReceiptWithStationCommand(
            DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $formData['issuedAt']) ?: new DateTimeImmutable(),
            [$line],
            $formData['stationName'],
            $formData['stationStreetName'],
            $formData['stationPostalCode'],
            $formData['stationCity'],
            $this->toNullableInt($formData['latitudeMicroDegrees']),
            $this->toNullableInt($formData['longitudeMicroDegrees']),
        );

        $receipt = ($this->createReceiptWithStationHandler)($command);
        $station = null;
        if (null !== $receipt->stationId()) {
            $station = $this->stationRepository->get($receipt->stationId()->toString());
        }

        $this->streamPublisher->publishCreated($receipt, $station);
    }

    private function toNullableInt(?string $value): ?int
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (null === $int) {
            return null;
        }

        return $int;
    }

    private function toRequiredInt(string $value, string $field): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (null === $int) {
            throw new UnexpectedValueException(sprintf('Expected integer for %s.', $field));
        }

        return $int;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $stringValue = trim($value);

        return '' === $stringValue ? null : $stringValue;
    }
}
