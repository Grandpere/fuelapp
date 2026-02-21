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

namespace App\Import\UI\Api\Resource\Output;

use DateTimeImmutable;

final readonly class ImportJobOutput
{
    /**
     * @param list<string>              $issues
     * @param array<string, mixed>|null $parsedDraft
     * @param array<string, mixed>|null $creationPayload
     */
    public function __construct(
        public string $id,
        public string $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $completedAt,
        public ?DateTimeImmutable $failedAt,
        public bool $reviewRequired,
        public bool $canAutoFinalize,
        public array $issues,
        public ?array $parsedDraft,
        public ?array $creationPayload,
        public ?string $fingerprint,
        public ?string $duplicateOfImportJobId,
        public ?string $finalizedReceiptId,
    ) {
    }
}
