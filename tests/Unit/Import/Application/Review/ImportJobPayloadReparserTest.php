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

namespace App\Tests\Unit\Import\Application\Review;

use App\Import\Application\Review\ImportJobPayloadReparser;
use App\Import\Domain\ImportJob;
use App\Import\Infrastructure\Parsing\RegexReceiptOcrParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImportJobPayloadReparserTest extends TestCase
{
    public function testItRebuildsNeedsReviewPayloadWithCurrentParserRules(): void
    {
        $job = ImportJob::createQueued(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            'local',
            '2026/02/21/file.jpg',
            'file.jpg',
            'image/jpeg',
            1024,
            str_repeat('a', 64),
        );
        $job->markNeedsReview(json_encode([
            'jobId' => $job->id()->toString(),
            'provider' => 'ocr_space',
            'fingerprint' => 'checksum-sha256:v1:'.str_repeat('a', 64),
            'text' => "PETRO EST\nLECLERC BELLE IDEE 10100 ROMILLY SUR SEINE\nle 14/12/24 a 15:07:08\nMONTANT REEL 40,32 EUR\nCarburant = GAZOLE\n= 25,25 L\nPrix unit. = 1,597 EUR\nTVA 20,00% = 6,72 EUR",
            'pages' => [],
            'parsedDraft' => [
                'stationStreetName' => null,
                'creationPayload' => null,
            ],
            'status' => 'needs_review',
        ], JSON_THROW_ON_ERROR));

        $reparser = new ImportJobPayloadReparser(new RegexReceiptOcrParser());
        $updated = $reparser->reparse($job);
        $payload = json_decode((string) $updated->errorPayload(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertSame('needs_review', $payload['status']);
        self::assertArrayHasKey('parsedDraft', $payload);
        self::assertIsArray($payload['parsedDraft']);
        self::assertSame('LECLERC BELLE IDEE', $payload['parsedDraft']['stationStreetName'] ?? null);
        self::assertArrayHasKey('creationPayload', $payload['parsedDraft']);
        self::assertIsArray($payload['parsedDraft']['creationPayload']);
        self::assertSame('LECLERC BELLE IDEE', $payload['parsedDraft']['creationPayload']['stationStreetName'] ?? null);
    }

    public function testItRejectsPayloadWithoutStoredOcrText(): void
    {
        $job = ImportJob::createQueued(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            'local',
            '2026/02/21/file.jpg',
            'file.jpg',
            'image/jpeg',
            1024,
            str_repeat('b', 64),
        );
        $job->markNeedsReview(json_encode([
            'jobId' => $job->id()->toString(),
            'provider' => 'ocr_unavailable_fallback',
            'text' => '',
            'pages' => [],
            'status' => 'needs_review',
        ], JSON_THROW_ON_ERROR));

        $reparser = new ImportJobPayloadReparser(new RegexReceiptOcrParser());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no OCR text is stored');
        $reparser->reparse($job);
    }
}
