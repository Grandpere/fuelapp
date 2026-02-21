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

namespace App\Import\UI\Web\Controller;

use App\Import\Application\Command\FinalizeImportJobCommand;
use App\Import\Application\Command\FinalizeImportJobHandler;
use App\Import\Application\Repository\ImportJobRepository;
use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Domain\Enum\FuelType;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use ValueError;

final class ImportJobFinalizeWebController extends AbstractController
{
    public function __construct(
        private readonly ImportJobRepository $importJobRepository,
        private readonly FinalizeImportJobHandler $finalizeImportJobHandler,
    ) {
    }

    #[Route('/ui/imports/{id}/finalize', name: 'ui_import_finalize', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['POST'])]
    public function __invoke(string $id, Request $request): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $job = $this->importJobRepository->get($id);
        if (null === $job) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('ui_import_finalize_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('ui_import_show', ['id' => $id]);
        }

        try {
            ($this->finalizeImportJobHandler)(new FinalizeImportJobCommand(
                $id,
                $this->toNullableDateTime($request->request->get('issuedAt')),
                $this->toNullableLines($request),
                $this->toNullableString($request->request->get('stationName')),
                $this->toNullableString($request->request->get('stationStreetName')),
                $this->toNullableString($request->request->get('stationPostalCode')),
                $this->toNullableString($request->request->get('stationCity')),
                $this->toNullableInt($request->request->get('latitudeMicroDegrees')),
                $this->toNullableInt($request->request->get('longitudeMicroDegrees')),
            ));
            $this->addFlash('success', 'Import finalized and receipt created.');
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('ui_import_show', ['id' => $id]);
    }

    private function toNullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $stringValue = trim((string) $value);

        return '' === $stringValue ? null : $stringValue;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $stringValue = trim((string) $value);
        if ('' === $stringValue) {
            return null;
        }

        $intValue = filter_var($stringValue, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return is_int($intValue) ? $intValue : null;
    }

    private function toNullableDateTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_scalar($value)) {
            return null;
        }

        $stringValue = trim((string) $value);
        if ('' === $stringValue) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $stringValue);
        if (false === $parsed) {
            return null;
        }

        return $parsed;
    }

    /** @return list<CreateReceiptLineCommand>|null */
    private function toNullableLines(Request $request): ?array
    {
        $fuelType = $this->toNullableString($request->request->get('lineFuelType'));
        $quantity = $this->toNullableInt($request->request->get('lineQuantityMilliLiters'));
        $unitPrice = $this->toNullableInt($request->request->get('lineUnitPriceDeciCentsPerLiter'));
        $vatRate = $this->toNullableInt($request->request->get('lineVatRatePercent'));

        if (null === $fuelType && null === $quantity && null === $unitPrice && null === $vatRate) {
            return null;
        }

        if (null === $fuelType || null === $quantity || null === $unitPrice || null === $vatRate) {
            throw new InvalidArgumentException('If one line field is provided, all line fields are required.');
        }

        try {
            $line = new CreateReceiptLineCommand(FuelType::from($fuelType), $quantity, $unitPrice, $vatRate);
        } catch (ValueError) {
            throw new InvalidArgumentException('Invalid fuel type provided.');
        }

        return [$line];
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
