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

    public function testItReconstructsStreetWhenSplitAcrossTwoLinesBeforePostalCity(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                E Leclerc L
                Petro - EST
                Route de
                Troyes
                51120 SEZANNE
                Le 06/04/24 a 11:44:33
                MONTANT REEL
                17,96 EUR
                Carburant = SP95 -E10
                Quantite = 9,56 L
                Prix unit. = 1,879 EUR
                TVA 20,00% = 2,99 EUR
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertSame('Route de Troyes', $draft->stationStreetName);
        self::assertSame('51120', $draft->stationPostalCode);
        self::assertSame('SEZANNE', $draft->stationCity);
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

    public function testItInfersFuelMetricsWhenNoisyLabelsAreShiftedAcrossLines(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                E Leclerc L
                Petro- EST
                Leclerc Centre Auto
                Route de Troyes
                51120 SEZANNE
                Le 20/02/26 a 13:55:36
                MONTANT REEL
                44, 42 EUR
                No pompe GAZOLE 1
                Carburant 28,64
                Quantite 551 EUR
                Prix unit. 7,40 EUR 1
                TVA 20,00%
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertCount(1, $draft->lines);
        self::assertSame('diesel', $draft->lines[0]->fuelType);
        self::assertSame(28_640, $draft->lines[0]->quantityMilliLiters);
        self::assertSame(1551, $draft->lines[0]->unitPriceDeciCentsPerLiter);
    }

    public function testItParsesUnitPriceFromPrixLabelWithoutPerLiterSuffix(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                STATION TEST
                12 Rue Exemple
                75010 PARIS
                Date 02/03/2026 14:30
                Pompe 3
                Volume
                Gazole
                40.40
                Prix
                1 769
                TOTAL TTC 71.47
                TVA 20.00 % 11.91
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertCount(1, $draft->lines);
        self::assertSame('diesel', $draft->lines[0]->fuelType);
        self::assertSame(40_400, $draft->lines[0]->quantityMilliLiters);
        self::assertSame(1769, $draft->lines[0]->unitPriceDeciCentsPerLiter);
    }

    public function testItParsesLuxembourgPostalFormatAndExcellium98Line(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                TOTAL
                TOTAL FRISANGE 40
                40 Rue Robert Schuman
                L-5751 FRISANGE
                TICKET CLIENT
                *Excellium 98 5 € 54.72
                (COL. 7; 51.24 l * € 1.068/l)
                TOTAL 54.72
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertSame('TOTAL', $draft->stationName);
        self::assertSame('40 Rue Robert Schuman', $draft->stationStreetName);
        self::assertSame('L-5751', $draft->stationPostalCode);
        self::assertSame('FRISANGE', $draft->stationCity);
        self::assertSame(5472, $draft->totalCents);
        self::assertCount(1, $draft->lines);
        self::assertSame('sp98', $draft->lines[0]->fuelType);
        self::assertSame(51_240, $draft->lines[0]->quantityMilliLiters);
        self::assertSame(1068, $draft->lines[0]->unitPriceDeciCentsPerLiter);
    }

    public function testItSkipsTechnicalTokenAsStreetCandidate(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                INTERMARCHE
                a0000000421010
                41300 NOYERS SUR CHER CARTE BANCAIRE
                MONTANT REEL 34.11 EUR
                Carburant = E10
                Prix unit. 1,619 EUR
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertSame('INTERMARCHE', $draft->stationName);
        self::assertNull($draft->stationStreetName);
        self::assertSame('41300', $draft->stationPostalCode);
        self::assertSame('NOYERS SUR CHER', $draft->stationCity);
        self::assertSame(3411, $draft->totalCents);
    }

    public function testItExtractsInlineLocationAliasBeforePostalCityWhenNoStreetLineExists(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                E Leclerc PETRO EST 0352780130 LECLERC BELLE IDEE 10100 ROMILLY SUR SEINE
                le 14/12/24 a 15:07:08
                MONTANT REEL 40,32 EUR
                Carburant = GAZOLE
                Quantite = 25,25 L
                Prix unit. = 1,597 EUR
                TVA 20,00% = 6,72 EUR
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertSame('LECLERC BELLE IDEE', $draft->stationStreetName);
        self::assertSame('10100', $draft->stationPostalCode);
        self::assertSame('ROMILLY SUR SEINE', $draft->stationCity);
        self::assertIsArray($draft->toArray()['creationPayload']);
    }

    public function testItParsesMontantReelWhenAmountAppearsBeforeLabelInCompactOcrLine(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            '... € 59.56 11.91 20.00 € 71.47 : 1.769/1 Gazole ... DEBIT EUR 71.47 MONTANT REEL ... 51120 SEZANNE PETRO EST LECLERC ...',
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertSame(7147, $draft->totalCents);
        self::assertSame('PETRO EST', $draft->stationName);
        self::assertSame('51120', $draft->stationPostalCode);
        self::assertSame('SEZANNE', $draft->stationCity);
        self::assertSame(1191, $draft->vatAmountCents);
        self::assertCount(1, $draft->lines);
        self::assertSame(1769, $draft->lines[0]->unitPriceDeciCentsPerLiter);
        self::assertSame(40400, $draft->lines[0]->quantityMilliLiters);
        self::assertSame(20, $draft->lines[0]->vatRatePercent);
        self::assertNotContains('fuel_line_quantity_missing', $draft->issues);
        self::assertNotContains('fuel_line_vat_rate_missing', $draft->issues);
    }

    public function testItFlagsMissingUnitPriceAndQuantityOnIncompleteFuelLine(): void
    {
        $ocr = new OcrExtraction(
            'ocr_space',
            <<<TXT
                STATION TEST
                12 Rue Exemple
                75010 PARIS
                Date 02/03/2026 14:30
                Gazole 71.47
                TOTAL TTC 71.47
                TVA 20.00 % 11.91
                TXT,
            [],
            [],
        );

        $parser = new RegexReceiptOcrParser();
        $draft = $parser->parse($ocr);

        self::assertContains('fuel_line_quantity_missing', $draft->issues);
        self::assertContains('fuel_line_unit_price_missing', $draft->issues);
        self::assertContains('fuel_lines_incomplete', $draft->issues);
        self::assertNull($draft->toArray()['creationPayload']);
    }
}
