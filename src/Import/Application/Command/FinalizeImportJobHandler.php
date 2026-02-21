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

namespace App\Import\Application\Command;

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Application\Storage\ImportFileStorage;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationHandler;
use App\Receipt\Domain\Enum\FuelType;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Throwable;
use ValueError;

final readonly class FinalizeImportJobHandler
{
    public function __construct(
        private ImportJobRepository $repository,
        private ImportFileStorage $fileStorage,
        private CreateReceiptWithStationHandler $createReceiptWithStationHandler,
    ) {
    }

    public function __invoke(FinalizeImportJobCommand $command): ImportJob
    {
        $job = $this->repository->getForSystem($command->importJobId);
        if (null === $job) {
            throw new InvalidArgumentException('Import job not found.');
        }

        if (ImportJobStatus::NEEDS_REVIEW !== $job->status()) {
            throw new InvalidArgumentException('Only needs_review jobs can be finalized.');
        }

        $payload = $this->decodePayload($job);

        $issuedAt = $command->issuedAt ?? $this->readDate($payload, 'creationPayload.issuedAt');
        $stationName = $this->coalesceString($command->stationName, $this->readString($payload, 'creationPayload.stationName'));
        $stationStreetName = $this->coalesceString($command->stationStreetName, $this->readString($payload, 'creationPayload.stationStreetName'));
        $stationPostalCode = $this->coalesceString($command->stationPostalCode, $this->readString($payload, 'creationPayload.stationPostalCode'));
        $stationCity = $this->coalesceString($command->stationCity, $this->readString($payload, 'creationPayload.stationCity'));
        $latitudeMicroDegrees = $command->latitudeMicroDegrees ?? $this->readInt($payload, 'creationPayload.latitudeMicroDegrees');
        $longitudeMicroDegrees = $command->longitudeMicroDegrees ?? $this->readInt($payload, 'creationPayload.longitudeMicroDegrees');
        $lines = $command->lines ?? $this->readLinesFromPayload($payload);

        if (
            null === $issuedAt
            || null === $stationName
            || null === $stationStreetName
            || null === $stationPostalCode
            || null === $stationCity
            || [] === $lines
        ) {
            throw new InvalidArgumentException('Missing required fields to finalize this import.');
        }

        $receipt = ($this->createReceiptWithStationHandler)(new CreateReceiptWithStationCommand(
            $issuedAt,
            $lines,
            $stationName,
            $stationStreetName,
            $stationPostalCode,
            $stationCity,
            $latitudeMicroDegrees,
            $longitudeMicroDegrees,
            ownerId: $job->ownerId(),
        ));

        $job->markProcessedWithPayload($this->buildProcessedPayload(
            $job->id()->toString(),
            $receipt->id()->toString(),
            null !== ($payload['creationPayload'] ?? null),
        ));
        $this->repository->save($job);
        $this->fileStorage->delete($job->storage(), $job->filePath());

        return $job;
    }

    /** @return array<string, mixed> */
    private function decodePayload(ImportJob $job): array
    {
        $payload = $job->errorPayload();
        if (null === $payload || '' === trim($payload)) {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function buildProcessedPayload(string $jobId, string $receiptId, bool $usedCreationPayload): string
    {
        $payload = [
            'jobId' => $jobId,
            'status' => 'processed',
            'finalizedReceiptId' => $receiptId,
            'source' => 'manual_review',
            'usedCreationPayload' => $usedCreationPayload,
            'finalizedAt' => new DateTimeImmutable()->format(DATE_ATOM),
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'processed_payload_serialization_failed';
        }

        return $encoded;
    }

    /** @return list<CreateReceiptLineCommand> */
    /**
     * @param array<string, mixed> $payload
     *
     * @return list<CreateReceiptLineCommand>
     */
    private function readLinesFromPayload(array $payload): array
    {
        $rawLines = $this->read($payload, 'creationPayload.lines');
        if (!is_array($rawLines)) {
            return [];
        }

        $lines = [];
        foreach ($rawLines as $rawLine) {
            if (!is_array($rawLine)) {
                continue;
            }

            $fuelType = $rawLine['fuelType'] ?? null;
            $quantityMilliLiters = $rawLine['quantityMilliLiters'] ?? null;
            $unitPriceDeciCentsPerLiter = $rawLine['unitPriceDeciCentsPerLiter'] ?? null;
            $vatRatePercent = $rawLine['vatRatePercent'] ?? null;

            if (
                !is_string($fuelType)
                || !is_int($quantityMilliLiters)
                || !is_int($unitPriceDeciCentsPerLiter)
                || !is_int($vatRatePercent)
            ) {
                continue;
            }

            try {
                $lines[] = new CreateReceiptLineCommand(
                    FuelType::from($fuelType),
                    $quantityMilliLiters,
                    $unitPriceDeciCentsPerLiter,
                    $vatRatePercent,
                );
            } catch (ValueError) {
                continue;
            }
        }

        return $lines;
    }

    /** @param array<string, mixed> $payload */
    private function readDate(array $payload, string $path): ?DateTimeImmutable
    {
        $value = $this->readString($payload, $path);
        if (null === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $payload */
    private function readString(array $payload, string $path): ?string
    {
        $value = $this->read($payload, $path);
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    /** @param array<string, mixed> $payload */
    private function readInt(array $payload, string $path): ?int
    {
        $value = $this->read($payload, $path);

        return is_int($value) ? $value : null;
    }

    /** @param array<string, mixed> $payload */
    private function read(array $payload, string $path): mixed
    {
        $parts = explode('.', $path);
        $cursor = $payload;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return null;
            }

            $cursor = $cursor[$part];
        }

        return $cursor;
    }

    private function coalesceString(?string $preferred, ?string $fallback): ?string
    {
        if (null !== $preferred && '' !== trim($preferred)) {
            return trim($preferred);
        }

        return $fallback;
    }
}
