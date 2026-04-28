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

namespace App\Tests\Integration\PublicFuelStation\Infrastructure\Console;

use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class SyncPublicFuelStationsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE public_fuel_station_sync_runs, public_fuel_stations RESTART IDENTITY CASCADE');
    }

    public function testCommandRejectsZeroLimitInsteadOfRunningUnboundedSync(): void
    {
        $tester = $this->commandTester();

        $exitCode = $tester->execute([
            '--source' => $this->writeCsv(),
            '--limit' => '0',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Option "limit" must be a positive integer.', $tester->getDisplay());
        self::assertSame(0, $this->countRows('public_fuel_stations'));
        self::assertSame(0, $this->countRows('public_fuel_station_sync_runs'));
    }

    private function commandTester(): CommandTester
    {
        $kernel = self::$kernel;
        if (!$kernel instanceof KernelInterface) {
            throw new RuntimeException('Kernel is not available.');
        }

        $application = new Application($kernel);
        $command = $application->find('app:public-fuel-stations:sync');

        return new CommandTester($command);
    }

    private function writeCsv(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fuelapp-public-sync-command-');
        self::assertIsString($path);
        file_put_contents($path, <<<'CSV'
            id;latitude;longitude;Code postal;pop;Adresse;Ville;Carburants disponibles;Automate 24-24 (oui/non);Services proposés
            1;4956900;364600;01000;R;Rue A;Bourg;Gazole;oui;Boutique
            CSV);

        return $path;
    }

    private function countRows(string $table): int
    {
        $count = $this->em->getConnection()->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));

        return is_numeric($count) ? (int) $count : 0;
    }
}
