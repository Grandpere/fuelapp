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
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Domain\Enum\FuelType;
use App\Shared\UI\Web\SafeReturnPathResolver;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
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
        private readonly SafeReturnPathResolver $safeReturnPathResolver,
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

        $returnTo = $this->safeReturnPathResolver->resolve(
            $request->request->get('_return_to'),
            $this->generateUrl('ui_import_index'),
        );
        $selectedSuggestion = $this->toNullableString($request->request->get('selectedSuggestion'));
        $nextReviewId = $this->shouldContinueToNextReview($request)
            ? $this->findNextReviewJobId($job)
            : null;

        if (!$this->isCsrfTokenValid('ui_import_finalize_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.csrf.invalid');

            return $this->redirectToRoute('ui_import_show', $this->showRouteParameters($id, $returnTo, $selectedSuggestion));
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
                $this->toNullableInt($request->request->get('odometerKilometers')),
                $this->toNullableString($request->request->get('selectedStationId')),
                $this->selectedSuggestionType($request),
                $this->selectedSuggestionId($request),
            ));
            if (null !== $nextReviewId && $this->shouldContinueToNextReview($request)) {
                $this->addFlash('success', 'import.flash.finalized_next');

                return $this->redirectToRoute('ui_import_show', ['id' => $nextReviewId, 'return_to' => $returnTo]);
            }

            if ($this->shouldContinueToNextReview($request)) {
                $this->addFlash('success', 'import.flash.finalized_done');

                return $this->redirect($returnTo);
            }

            $this->addFlash('success', 'import.flash.finalized_created');
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('ui_import_show', $this->showRouteParameters($id, $returnTo, $selectedSuggestion));
    }

    private function shouldContinueToNextReview(Request $request): bool
    {
        return '1' === (string) $request->request->get('_continue');
    }

    private function findNextReviewJobId(ImportJob $currentJob): ?string
    {
        if (ImportJobStatus::NEEDS_REVIEW !== $currentJob->status()) {
            return null;
        }

        $queue = [];
        foreach ($this->importJobRepository->all() as $job) {
            if ($job->ownerId() !== $currentJob->ownerId() || ImportJobStatus::NEEDS_REVIEW !== $job->status()) {
                continue;
            }

            $queue[] = $job;
        }

        usort(
            $queue,
            static function (ImportJob $left, ImportJob $right): int {
                $createdAtOrder = $right->createdAt()->getTimestamp() <=> $left->createdAt()->getTimestamp();
                if (0 !== $createdAtOrder) {
                    return $createdAtOrder;
                }

                return strcmp($right->id()->toString(), $left->id()->toString());
            },
        );

        foreach ($queue as $index => $job) {
            if ($job->id()->toString() !== $currentJob->id()->toString()) {
                continue;
            }

            $nextJob = $queue[$index + 1] ?? null;

            return $nextJob instanceof ImportJob ? $nextJob->id()->toString() : null;
        }

        return null;
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

    private function selectedSuggestionType(Request $request): ?string
    {
        $selectedSuggestion = $this->toNullableString($request->request->get('selectedSuggestion'));
        if (null === $selectedSuggestion) {
            return null;
        }

        [$type] = explode(':', $selectedSuggestion, 2);
        $type = trim($type);

        return '' === $type ? null : $type;
    }

    private function selectedSuggestionId(Request $request): ?string
    {
        $selectedSuggestion = $this->toNullableString($request->request->get('selectedSuggestion'));
        if (null === $selectedSuggestion) {
            return null;
        }

        $parts = explode(':', $selectedSuggestion, 2);
        $id = trim((string) ($parts[1] ?? ''));

        return '' === $id ? null : $id;
    }

    /**
     * @return array{id:string, return_to:string, selectedSuggestion?:string}
     */
    private function showRouteParameters(string $id, string $returnTo, ?string $selectedSuggestion): array
    {
        $parameters = [
            'id' => $id,
            'return_to' => $returnTo,
        ];

        if (null !== $selectedSuggestion) {
            $parameters['selectedSuggestion'] = $selectedSuggestion;
        }

        return $parameters;
    }

    /** @return list<CreateReceiptLineCommand>|null */
    private function toNullableLines(Request $request): ?array
    {
        $rawLines = $request->request->all('lines');
        if ([] !== $rawLines) {
            return $this->mapSubmittedLines($rawLines);
        }

        return $this->mapLegacyLineFields($request);
    }

    /**
     * @param array<int|string, mixed> $rawLines
     *
     * @return list<CreateReceiptLineCommand>|null
     */
    private function mapSubmittedLines(array $rawLines): ?array
    {
        $lines = [];
        $lineNumber = 0;

        foreach ($rawLines as $rawLine) {
            ++$lineNumber;
            if (!is_array($rawLine)) {
                continue;
            }

            $fuelType = $this->toNullableString($rawLine['fuelType'] ?? null);
            $quantity = $this->toNullableInt($rawLine['quantityMilliLiters'] ?? null);
            $unitPrice = $this->toNullableInt($rawLine['unitPriceDeciCentsPerLiter'] ?? null);
            $vatRate = $this->toNullableInt($rawLine['vatRatePercent'] ?? null);

            if (null === $fuelType && null === $quantity && null === $unitPrice && null === $vatRate) {
                continue;
            }

            if (null === $fuelType || null === $quantity || null === $unitPrice || null === $vatRate) {
                throw new InvalidArgumentException(strtr('import.validation.line_incomplete', ['%index%' => (string) $lineNumber]));
            }

            try {
                $lines[] = new CreateReceiptLineCommand(FuelType::from($fuelType), $quantity, $unitPrice, $vatRate);
            } catch (ValueError) {
                throw new InvalidArgumentException(strtr('import.validation.line_invalid_fuel_type', ['%index%' => (string) $lineNumber]));
            }
        }

        return [] === $lines ? null : $lines;
    }

    /** @return list<CreateReceiptLineCommand>|null */
    private function mapLegacyLineFields(Request $request): ?array
    {
        $fuelType = $this->toNullableString($request->request->get('lineFuelType'));
        $quantity = $this->toNullableInt($request->request->get('lineQuantityMilliLiters'));
        $unitPrice = $this->toNullableInt($request->request->get('lineUnitPriceDeciCentsPerLiter'));
        $vatRate = $this->toNullableInt($request->request->get('lineVatRatePercent'));

        if (null === $fuelType && null === $quantity && null === $unitPrice && null === $vatRate) {
            return null;
        }

        if (null === $fuelType || null === $quantity || null === $unitPrice || null === $vatRate) {
            throw new InvalidArgumentException('import.validation.legacy_line_incomplete');
        }

        try {
            $line = new CreateReceiptLineCommand(FuelType::from($fuelType), $quantity, $unitPrice, $vatRate);
        } catch (ValueError) {
            throw new InvalidArgumentException('import.validation.legacy_line_invalid_fuel_type');
        }

        return [$line];
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
