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

namespace App\Tests\Unit\Import\Infrastructure\Parsing;

use App\Import\Application\Ocr\OcrExtraction;
use App\Import\Infrastructure\Parsing\RegexReceiptOcrParser;
use PHPUnit\Framework\TestCase;

final class RegexReceiptOcrParserTest extends TestCase
{
    public function testItParsesAndNormalizesReceiptDraftFromOcrText(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                TOTAL ENERGIES
                1 Rue de Rivoli
                75001 Paris
                Date: 21/02/2026 10:45
                Diesel 40,00 L 1,879 €/L 75,16
                TVA 20% 12,53
                TOTAL TTC 75,16
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertSame('TOTAL ENERGIES', $draft->stationName);
        self::assertSame('1 Rue de Rivoli', $draft->stationStreetName);
        self::assertSame('75001', $draft->stationPostalCode);
        self::assertSame('Paris', $draft->stationCity);
        self::assertNotNull($draft->issuedAt);
        self::assertSame(7516, $draft->totalCents);
        self::assertSame(1253, $draft->vatAmountCents);
        self::assertCount(1, $draft->lines);
        self::assertSame('diesel', $draft->lines[0]->fuelType);
        self::assertSame(40_000, $draft->lines[0]->quantityMilliLiters);
        self::assertSame(1879, $draft->lines[0]->unitPriceDeciCentsPerLiter);

        $asArray = $draft->toArray();
        self::assertIsArray($asArray['creationPayload']);
        self::assertSame('TOTAL ENERGIES', $asArray['creationPayload']['stationName']);
        /** @var array{lines: list<array{fuelType: string}>} $creationPayload */
        $creationPayload = $asArray['creationPayload'];
        self::assertSame('diesel', $creationPayload['lines'][0]['fuelType']);
    }

    public function testItKeepsIssuesWhenParsedDataIsIncomplete(): void
    {
        $ocr = new OcrExtraction('ocr_space', 'TOTAL 80,00', ['TOTAL 80,00'], []);

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertNotEmpty($draft->issues);
        self::assertContains('fuel_lines_missing', $draft->issues);

        $asArray = $draft->toArray();
        self::assertNull($asArray['creationPayload']);
    }
}
