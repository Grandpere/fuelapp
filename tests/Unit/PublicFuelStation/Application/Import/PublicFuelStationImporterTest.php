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

namespace App\Tests\Unit\PublicFuelStation\Application\Import;

use App\PublicFuelStation\Application\Import\ParsedPublicFuelStation;
use App\PublicFuelStation\Application\Import\PublicFuelStationCsvParser;
use App\PublicFuelStation\Application\Import\PublicFuelStationImporter;
use App\PublicFuelStation\Application\Import\PublicFuelStationImportException;
use App\PublicFuelStation\Application\Repository\PublicFuelStationRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicFuelStationImporterTest extends TestCase
{
    public function testItRejectsRowsWithoutCoordinatesAndRespectsLimit(): void
    {
        $path = $this->writeCsv(<<<'CSV'
            id;latitude;longitude;Code postal;pop;Adresse;Ville;Carburants disponibles;Automate 24-24 (oui/non);Services proposés
            1;4956900;364600;01000;R;Rue A;Bourg;Gazole;oui;Boutique
            2;;;01000;R;Rue B;Bourg;SP95;non;
            3;4957000;364700;01000;R;Rue C;Bourg;E10;non;
            CSV);
        $repository = new RecordingPublicFuelStationRepository();
        $importer = new PublicFuelStationImporter(new PublicFuelStationCsvParser(), $repository);

        $result = $importer->importFile($path, 2);

        self::assertSame(2, $result->processedCount);
        self::assertSame(1, $result->upsertedCount);
        self::assertSame(1, $result->rejectedCount);
        self::assertCount(1, $repository->stations);
        self::assertSame('1', $repository->stations[0]->sourceId);
    }

    public function testItReportsPartialCountsWhenRepositoryFailsMidImport(): void
    {
        $path = $this->writeCsv(<<<'CSV'
            id;latitude;longitude;Code postal;pop;Adresse;Ville;Carburants disponibles;Automate 24-24 (oui/non);Services proposés
            1;4956900;364600;01000;R;Rue A;Bourg;Gazole;oui;Boutique
            2;4957000;364700;01000;R;Rue B;Bourg;E10;non;
            CSV);
        $repository = new FailingPublicFuelStationRepository();
        $importer = new PublicFuelStationImporter(new PublicFuelStationCsvParser(), $repository);

        try {
            $importer->importFile($path);
            self::fail('Expected import exception.');
        } catch (PublicFuelStationImportException $e) {
            self::assertSame(2, $e->processedCount);
            self::assertSame(1, $e->upsertedCount);
            self::assertSame(0, $e->rejectedCount);
            self::assertSame('Simulated repository failure.', $e->getMessage());
        }
    }

    private function writeCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fuelapp-public-importer-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return $path;
    }
}

final class RecordingPublicFuelStationRepository implements PublicFuelStationRepository
{
    /** @var list<ParsedPublicFuelStation> */
    public array $stations = [];

    public function upsert(ParsedPublicFuelStation $station): void
    {
        $this->stations[] = $station;
    }

    public function countAll(): int
    {
        return count($this->stations);
    }
}

final class FailingPublicFuelStationRepository implements PublicFuelStationRepository
{
    private int $calls = 0;

    public function upsert(ParsedPublicFuelStation $station): void
    {
        ++$this->calls;
        if (2 === $this->calls) {
            throw new RuntimeException('Simulated repository failure.');
        }
    }

    public function countAll(): int
    {
        return 0;
    }
}
