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

use App\PublicFuelStation\Application\Import\PublicFuelStationCsvParser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PublicFuelStationCsvParserTest extends TestCase
{
    public function testItParsesInstantV2FuelStationRows(): void
    {
        $path = $this->writeCsv(<<<'CSV'
            id;latitude;longitude;Code postal;pop;Adresse;Ville;Prix Gazole mis à jour le;Prix Gazole;Prix SP95 mis à jour le;Prix SP95;Prix E85 mis à jour le;Prix E85;Prix GPLc mis à jour le;Prix GPLc;Prix E10 mis à jour le;Prix E10;Prix SP98 mis à jour le;Prix SP98;Début rupture e10 (si temporaire);Type rupture e10;Début rupture sp98 (si temporaire);Type rupture sp98;Carburants disponibles;Carburants en rupture temporaire;Carburants en rupture definitive;Automate 24-24 (oui/non);Services proposés;Département;code_departement;Région;code_region
            1000001;4956900;364600;01000;R;596 AVENUE DE TREVOUX;SAINT-DENIS-LÈS-BOURG;2026-04-28T09:15:00+02:00;1.789;2026-04-28T09:20:00+02:00;1,899;2026-04-27T08:00:00+02:00;0.849;;;2026-04-28T09:30:00+02:00;1.712;;;2026-04-28T10:00:00+02:00;temporaire;2026-04-28T11:00:00+02:00;definitive;Gazole, SP95, E85, E10;E10;SP98;oui;Boutique alimentaire, Station de gonflage;Ain;01;Auvergne-Rhône-Alpes;84
            CSV);

        $stations = iterator_to_array(new PublicFuelStationCsvParser()->parseFile($path));

        self::assertCount(1, $stations);
        $station = $stations[0];
        self::assertSame('1000001', $station->sourceId);
        self::assertSame(49569000, $station->latitudeMicroDegrees);
        self::assertSame(3646000, $station->longitudeMicroDegrees);
        self::assertSame('596 AVENUE DE TREVOUX', $station->address);
        self::assertSame('01000', $station->postalCode);
        self::assertSame('SAINT-DENIS-LÈS-BOURG', $station->city);
        self::assertSame('R', $station->populationKind);
        self::assertTrue($station->automate24);
        self::assertSame(['Boutique alimentaire', 'Station de gonflage'], $station->services);
        self::assertSame('Ain', $station->department);
        self::assertSame('01', $station->departmentCode);
        self::assertSame('Auvergne-Rhône-Alpes', $station->region);
        self::assertSame('84', $station->regionCode);
        self::assertInstanceOf(DateTimeImmutable::class, $station->sourceUpdatedAt);
        self::assertSame('2026-04-28T09:30:00+02:00', $station->sourceUpdatedAt->format(DATE_ATOM));

        self::assertSame([
            'available' => true,
            'priceMilliEurosPerLiter' => 1789,
            'priceUpdatedAt' => '2026-04-28T09:15:00+02:00',
            'ruptureType' => null,
            'ruptureStartedAt' => null,
        ], $station->fuels['gazole']);
        self::assertSame(1899, $station->fuels['sp95']['priceMilliEurosPerLiter']);
        self::assertFalse($station->fuels['e10']['available']);
        self::assertSame('temporaire', $station->fuels['e10']['ruptureType']);
        self::assertSame('2026-04-28T10:00:00+02:00', $station->fuels['e10']['ruptureStartedAt']);
        self::assertFalse($station->fuels['sp98']['available']);
        self::assertSame('definitive', $station->fuels['sp98']['ruptureType']);
    }

    public function testItLeavesSourceUpdatedAtNullWhenFeedHasNoSourceDate(): void
    {
        $path = $this->writeCsv(<<<'CSV'
            id;latitude;longitude;Code postal;pop;Adresse;Ville;Prix Gazole mis à jour le;Prix Gazole;Carburants disponibles;Automate 24-24 (oui/non);Services proposés
            1000002;4956900;364600;01000;R;596 AVENUE DE TREVOUX;SAINT-DENIS-LÈS-BOURG;;1.789;Gazole;oui;Boutique alimentaire
            CSV);

        $stations = iterator_to_array(new PublicFuelStationCsvParser()->parseFile($path));

        self::assertCount(1, $stations);
        self::assertNull($stations[0]->sourceUpdatedAt);
    }

    private function writeCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fuelapp-public-parser-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
