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

namespace App\Import\UI\Api\State;

use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\Import\UI\Api\Resource\Output\ImportJobOutput;
use JsonException;

final class ImportJobOutputMapper
{
    public function map(ImportJob $job): ImportJobOutput
    {
        $payload = $this->decodePayload($job->errorPayload());

        $parsedDraft = $this->readArray($payload, 'parsedDraft');
        $creationPayload = $this->readArray($payload, 'creationPayload');
        $issues = $this->readStringList($parsedDraft, 'issues');

        return new ImportJobOutput(
            $job->id()->toString(),
            $job->status()->value,
            $job->createdAt(),
            $job->updatedAt(),
            $job->startedAt(),
            $job->completedAt(),
            $job->failedAt(),
            ImportJobStatus::NEEDS_REVIEW === $job->status(),
            null !== $creationPayload,
            $issues,
            $parsedDraft,
            $creationPayload,
            $this->readString($payload, 'fingerprint'),
            $this->readString($payload, 'duplicateOfImportJobId'),
            $this->readString($payload, 'finalizedReceiptId'),
        );
    }

    /** @return array<string, mixed> */
    private function decodePayload(?string $payload): array
    {
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

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function readArray(array $payload, string $key): ?array
    {
        $value = $payload[$key] ?? null;
        if (!is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /** @return list<string> */
    /**
     * @param array<string, mixed>|null $payload
     *
     * @return list<string>
     */
    private function readStringList(?array $payload, string $key): array
    {
        if (null === $payload) {
            return [];
        }

        $value = $payload[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_string($item) && '' !== trim($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $payload */
    private function readString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
