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

namespace App\Import\Application\Review;

use App\Import\Application\Ocr\OcrExtraction;
use App\Import\Application\Parsing\ParsedReceiptDraft;
use App\Import\Application\Parsing\ReceiptOcrParser;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use InvalidArgumentException;
use JsonException;

final readonly class ImportJobPayloadReparser
{
    public function __construct(private ReceiptOcrParser $receiptParser)
    {
    }

    public function reparse(ImportJob $job): ImportJob
    {
        if (ImportJobStatus::NEEDS_REVIEW !== $job->status()) {
            throw new InvalidArgumentException('Only imports in needs_review status can be reparsed.');
        }

        $payload = $this->decodePayload($job->errorPayload());
        $provider = $this->readString($payload, 'provider') ?? 'ocr_space';
        $text = $this->readString($payload, 'text');
        $pages = $this->readPages($payload);
        if ((null === $text || '' === trim($text)) && [] === $pages) {
            throw new InvalidArgumentException('This import cannot be reparsed because no OCR text is stored.');
        }

        $draft = $this->receiptParser->parse(new OcrExtraction(
            $provider,
            $text ?? implode("\n\n", $pages),
            $pages,
            [],
        ));

        $job->markNeedsReview($this->buildNeedsReviewPayload($payload, $job->id()->toString(), $provider, $text ?? '', $pages, $draft));

        return $job;
    }

    /** @return array<string, mixed> */
    private function decodePayload(?string $payload): array
    {
        if (null === $payload || '' === trim($payload)) {
            throw new InvalidArgumentException('This import does not have a stored review payload.');
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Stored import payload is invalid and cannot be reparsed.');
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Stored import payload is invalid and cannot be reparsed.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @param array<string, mixed> $payload */
    private function readString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function readPages(array $payload): array
    {
        $pages = $payload['pages'] ?? null;
        if (!is_array($pages)) {
            return [];
        }

        $normalizedPages = [];
        foreach ($pages as $page) {
            if (!is_string($page)) {
                continue;
            }

            $page = trim($page);
            if ('' === $page) {
                continue;
            }

            $normalizedPages[] = $page;
        }

        return $normalizedPages;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $pages
     */
    private function buildNeedsReviewPayload(array $payload, string $jobId, string $provider, string $text, array $pages, ParsedReceiptDraft $draft): string
    {
        $rebuiltPayload = [
            'jobId' => $jobId,
            'fingerprint' => $this->readString($payload, 'fingerprint'),
            'provider' => $provider,
            'text' => mb_substr($text, 0, 2000),
            'pages' => $pages,
            'parsedDraft' => $draft->toArray(),
            'status' => 'needs_review',
        ];

        try {
            return json_encode($rebuiltPayload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Import payload could not be rebuilt after reparsing.');
        }
    }
}
