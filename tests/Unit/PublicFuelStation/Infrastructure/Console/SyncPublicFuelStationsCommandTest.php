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

namespace App\Tests\Unit\PublicFuelStation\Infrastructure\Console;

use App\PublicFuelStation\Application\Import\ParsedPublicFuelStation;
use App\PublicFuelStation\Application\Import\PublicFuelStationCsvParser;
use App\PublicFuelStation\Application\Import\PublicFuelStationImporter;
use App\PublicFuelStation\Application\Repository\PublicFuelStationRepository;
use App\PublicFuelStation\Application\Repository\PublicFuelStationSyncRunRepository;
use App\PublicFuelStation\Infrastructure\Console\SyncPublicFuelStationsCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SyncPublicFuelStationsCommandTest extends TestCase
{
    public function testItPersistsPartialCountsWhenImportFailsMidRun(): void
    {
        $importer = new PublicFuelStationImporter(new PublicFuelStationCsvParser(), new FailingOnSecondUpsertRepository());
        $syncRunRepository = new RecordingSyncRunRepository();
        $httpClient = $this->createMock(HttpClientInterface::class);
        $tester = new CommandTester(new SyncPublicFuelStationsCommand($importer, $syncRunRepository, $httpClient));

        $exitCode = $tester->execute(['--source' => $this->writeCsv()]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame([
            'id' => 'run-1',
            'status' => 'failed',
            'processedCount' => 2,
            'upsertedCount' => 1,
            'rejectedCount' => 0,
            'errorMessage' => 'Simulated repository failure.',
        ], $syncRunRepository->finishedRun);
    }

    private function writeCsv(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fuelapp-public-sync-command-unit-');
        self::assertIsString($path);
        file_put_contents($path, <<<'CSV'
            id;latitude;longitude;Code postal;pop;Adresse;Ville;Carburants disponibles;Automate 24-24 (oui/non);Services proposés
            1;4956900;364600;01000;R;Rue A;Bourg;Gazole;oui;Boutique
            2;4957000;364700;01000;R;Rue B;Bourg;SP95;oui;Boutique
            CSV);

        return $path;
    }
}

final class RecordingSyncRunRepository implements PublicFuelStationSyncRunRepository
{
    /** @var array{id:string,status:string,processedCount:int,upsertedCount:int,rejectedCount:int,errorMessage:?string}|null */
    public ?array $finishedRun = null;

    public function start(string $sourceUrl): string
    {
        return 'run-1';
    }

    public function finish(string $id, string $status, int $processedCount, int $upsertedCount, int $rejectedCount, ?string $errorMessage = null): void
    {
        $this->finishedRun = [
            'id' => $id,
            'status' => $status,
            'processedCount' => $processedCount,
            'upsertedCount' => $upsertedCount,
            'rejectedCount' => $rejectedCount,
            'errorMessage' => $errorMessage,
        ];
    }
}

final class FailingOnSecondUpsertRepository implements PublicFuelStationRepository
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
