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
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use UnexpectedValueException;

final class CreateReceiptController extends AbstractController
{
    public function __construct(
        private readonly CreateReceiptWithStationHandler $createReceiptWithStationHandler,
        private readonly StationRepository $stationRepository,
        private readonly ReceiptStreamPublisher $streamPublisher,
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly ValidatorInterface $validator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/receipts/new', name: 'ui_receipt_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $isTurboFrameRequest = $request->headers->has('Turbo-Frame');
        $formData = $this->defaultFormData($request, $ownerId);
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData = $this->extractFormData($request, $ownerId);
            if (!$this->isCsrfTokenValid('receipt_new', $formData['_token'])) {
                $errors[] = 'Jeton CSRF invalide.';
            } else {
                $errors = $this->validateFormData($formData, $ownerId);
                if ([] === $errors) {
                    $this->persistReceiptFromForm($formData, $ownerId);
                    $this->addFlash('success', 'Receipt created.');

                    return new RedirectResponse($this->generateUrl('ui_receipt_index'), Response::HTTP_SEE_OTHER);
                }
            }
        }

        $response = $this->render($isTurboFrameRequest ? 'receipt/_form.html.twig' : 'receipt/new.html.twig', [
            'formData' => $formData,
            'errors' => $errors,
            'fuelTypes' => array_map(static fn (FuelType $fuelType): string => $fuelType->value, FuelType::cases()),
            'vehicleOptions' => $this->vehicleOptions($ownerId),
            'csrfToken' => $this->csrfTokenManager->getToken('receipt_new')->getValue(),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /** @return array<string, string> */
    private function defaultFormData(Request $request, string $ownerId): array
    {
        return [
            'issuedAt' => new DateTimeImmutable()->format('Y-m-d\TH:i'),
            'vehicleId' => $this->readPrefilledVehicleId($request, $ownerId) ?? '',
            'fuelType' => FuelType::DIESEL->value,
            'quantityLiters' => '',
            'unitPriceEurosPerLiter' => '',
            'vatRatePercent' => '20',
            'stationName' => '',
            'stationStreetName' => '',
            'stationPostalCode' => '',
            'stationCity' => '',
            'latitudeMicroDegrees' => '',
            'longitudeMicroDegrees' => '',
            'odometerKilometers' => '',
            '_token' => '',
        ];
    }

    /** @return array<string, string> */
    private function extractFormData(Request $request, string $ownerId): array
    {
        $data = $this->defaultFormData($request, $ownerId);

        foreach (array_keys($data) as $key) {
            $value = $request->request->get($key, '');
            $data[$key] = is_scalar($value) ? (string) $value : '';
        }

        return $data;
    }

    /** @param array<string, string> $formData
     * @return list<string>
     */
    private function validateFormData(array $formData, string $ownerId): array
    {
        $issuedAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $formData['issuedAt']) ?: null;
        $quantityMilliLiters = $this->parseScaledDecimalToInt($formData['quantityLiters'], 1000, 3);
        $unitPriceDeciCentsPerLiter = $this->parseScaledDecimalToInt($formData['unitPriceEurosPerLiter'], 1000, 3);
        $vatRatePercent = $this->toNullableInt($formData['vatRatePercent']);
        $vehicleId = $this->nullIfEmpty($formData['vehicleId']);

        $lineInput = new ReceiptLineInput(
            $this->nullIfEmpty($formData['fuelType']),
            $quantityMilliLiters,
            $unitPriceDeciCentsPerLiter,
            $vatRatePercent,
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
            $vehicleId,
            $this->toNullableInt($formData['odometerKilometers']),
        );

        $errors = [];
        if (null === $issuedAt) {
            $errors[] = 'Invalid issue date.';
        }
        if (null === $quantityMilliLiters) {
            $errors[] = 'Quantity must be a valid liters value, for example 40.40.';
        }
        if (null === $unitPriceDeciCentsPerLiter) {
            $errors[] = 'Unit price must be a valid €/L value, for example 1.769.';
        }
        if (null === $vatRatePercent) {
            $errors[] = 'VAT must be an integer percentage.';
        }
        if (null !== $vehicleId && !$this->vehicleRepository->belongsToOwner($vehicleId, $ownerId)) {
            $errors[] = 'Vehicle not found.';
        }

        foreach ($this->validator->validate($receiptInput) as $violation) {
            $errors[] = (string) $violation->getMessage();
        }

        return array_values(array_unique($errors));
    }

    /** @param array<string, string> $formData */
    private function persistReceiptFromForm(array $formData, string $ownerId): void
    {
        $quantityMilliLiters = $this->parseScaledDecimalToRequiredInt($formData['quantityLiters'], 1000, 3, 'quantityLiters');
        $unitPriceDeciCentsPerLiter = $this->parseScaledDecimalToRequiredInt($formData['unitPriceEurosPerLiter'], 1000, 3, 'unitPriceEurosPerLiter');

        $line = new CreateReceiptLineCommand(
            FuelType::from($formData['fuelType']),
            $quantityMilliLiters,
            $unitPriceDeciCentsPerLiter,
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
            $this->nullIfEmpty($formData['vehicleId']),
            $ownerId,
            odometerKilometers: $this->toNullableInt($formData['odometerKilometers']),
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

    private function parseScaledDecimalToRequiredInt(string $value, int $scale, int $maxDecimals, string $field): int
    {
        $parsed = $this->parseScaledDecimalToInt($value, $scale, $maxDecimals);
        if (null === $parsed) {
            throw new UnexpectedValueException(sprintf('Expected decimal value for %s.', $field));
        }

        return $parsed;
    }

    private function parseScaledDecimalToInt(?string $value, int $scale, int $maxDecimals): ?int
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim(str_replace(' ', '', str_replace(',', '.', $value)));
        if ('' === $normalized) {
            return null;
        }

        if (!preg_match('/^\d+(?:\.\d{1,'.$maxDecimals.'})?$/', $normalized)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad($fraction, $maxDecimals, '0');

        return ((int) $whole * $scale) + (int) substr($fraction, 0, $maxDecimals);
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $stringValue = trim($value);

        return '' === $stringValue ? null : $stringValue;
    }

    /** @return array<string, string> */
    private function vehicleOptions(string $ownerId): array
    {
        $options = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            if ($vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $options[$vehicle->id()->toString()] = sprintf('%s (%s)', $vehicle->name(), $vehicle->plateNumber());
        }

        return $options;
    }

    private function readPrefilledVehicleId(Request $request, string $ownerId): ?string
    {
        $raw = $request->query->get('vehicle_id');
        if (!is_scalar($raw)) {
            return null;
        }

        $vehicleId = trim((string) $raw);
        if ('' === $vehicleId || !Uuid::isValid($vehicleId)) {
            return null;
        }

        return $this->vehicleRepository->belongsToOwner($vehicleId, $ownerId) ? $vehicleId : null;
    }
}
