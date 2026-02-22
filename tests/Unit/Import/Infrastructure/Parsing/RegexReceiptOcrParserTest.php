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

    public function testItParsesLeclercStyleMultilineTicket(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                E Leclerc L
                Petro - EST
                Leclerc Centre Auto
                Route de Troyes
                51120 SEZANNE
                Le 06/04/24 a 11:44:33
                MONTANT REEL
                17,96 EUR
                Carburant = SP95 -E10
                Quantite
                = 9,56
                L
                Prix unit. = 1,879 EUR
                TVA 20,00% = 2,99 EUR
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertSame('E Leclerc L', $draft->stationName);
        self::assertSame('Route de Troyes', $draft->stationStreetName);
        self::assertSame('51120', $draft->stationPostalCode);
        self::assertSame('SEZANNE', $draft->stationCity);
        self::assertNotNull($draft->issuedAt);
        self::assertSame(1796, $draft->totalCents);
        self::assertCount(1, $draft->lines);
        self::assertSame('sp95', $draft->lines[0]->fuelType);
        self::assertSame(9_560, $draft->lines[0]->quantityMilliLiters);
        self::assertSame(1879, $draft->lines[0]->unitPriceDeciCentsPerLiter);
        self::assertSame(20, $draft->lines[0]->vatRatePercent);
        self::assertIsArray($draft->toArray()['creationPayload']);
    }

    public function testItParsesLeclercTicketWithSplitUnitPrice(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                E Leclerc L
                Petro - EST
                Route de Troyes
                51120 SEZANNE
                Le 20/02/26 a 13:55:36
                MONTANT REEL
                44, 42 EUR
                Carburant
                GAZOLE
                Quantite
                28,64
                Prix unit.
                1
                551 EUR
                TVA 20,00% 4
                7,40 EUR
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertNotNull($draft->issuedAt);
        self::assertSame(4442, $draft->totalCents);
        self::assertCount(1, $draft->lines);
        self::assertSame('diesel', $draft->lines[0]->fuelType);
        self::assertSame(28_640, $draft->lines[0]->quantityMilliLiters);
        self::assertSame(1551, $draft->lines[0]->unitPriceDeciCentsPerLiter);
        self::assertSame(20, $draft->lines[0]->vatRatePercent);
        self::assertIsArray($draft->toArray()['creationPayload']);
    }

    public function testItParsesVolumeAndPriceFromNoisyPumpTicket(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                PETRO EST
                LECLERC SEZANNE HYPER
                51120 SEZANNE
                TEL 03 52 78 01 30
                Date 06-02-2024 11:55:33
                Pompe 4
                Volume
                Prix
                Gazole
                40.40 ₽
                1. 769/8
                € 71.47
                TVA
                20.00 %
                11.91
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertSame('diesel', $draft->lines[0]->fuelType);
        self::assertSame(40_400, $draft->lines[0]->quantityMilliLiters);
        self::assertSame(1769, $draft->lines[0]->unitPriceDeciCentsPerLiter);
        self::assertSame(20, $draft->lines[0]->vatRatePercent);
    }
}
