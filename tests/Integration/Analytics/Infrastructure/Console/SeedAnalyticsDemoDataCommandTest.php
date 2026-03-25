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

namespace App\Tests\Integration\Analytics\Infrastructure\Console;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class SeedAnalyticsDemoDataCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;
        $this->connection = $em->getConnection();

        $this->connection->executeStatement('TRUNCATE TABLE analytics_daily_fuel_kpis, analytics_projection_states, admin_audit_logs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, import_jobs, receipt_lines, receipts, stations, vehicles, user_identities, users CASCADE');
    }

    public function testCommandSeedsDedicatedDemoAnalyticsDatasetAndCanBeRerun(): void
    {
        $tester = $this->commandTester();

        $exitCode = $tester->execute([
            '--email' => 'demo.analytics@test.local',
            '--password' => 'secret-demo-password',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Analytics demo data ready for demo.analytics@test.local', $tester->getDisplay());

        $user = $this->em->getRepository(UserEntity::class)->findOneBy(['email' => 'demo.analytics@test.local']);
        self::assertInstanceOf(UserEntity::class, $user);
        self::assertSame(8, $this->countRows('receipts', 'owner_id', $user->getId()->toRfc4122()));
        self::assertSame(3, $this->countRows('maintenance_events', 'owner_id', $user->getId()->toRfc4122()));
        self::assertSame(2, $this->countRows('vehicles', 'owner_id', $user->getId()->toRfc4122()));
        self::assertGreaterThan(0, $this->countRows('analytics_daily_fuel_kpis', 'owner_id', $user->getId()->toRfc4122()));

        $secondRunExitCode = $tester->execute([
            '--email' => 'demo.analytics@test.local',
            '--password' => 'secret-demo-password',
        ]);

        self::assertSame(0, $secondRunExitCode);
        self::assertSame(8, $this->countRows('receipts', 'owner_id', $user->getId()->toRfc4122()));
        self::assertSame(3, $this->countRows('maintenance_events', 'owner_id', $user->getId()->toRfc4122()));
        self::assertSame(2, $this->countRows('vehicles', 'owner_id', $user->getId()->toRfc4122()));
    }

    private function commandTester(): CommandTester
    {
        $kernel = self::$kernel;
        if (!$kernel instanceof KernelInterface) {
            throw new RuntimeException('Kernel is not available.');
        }

        $application = new Application($kernel);
        $command = $application->find('app:analytics:demo-seed');

        return new CommandTester($command);
    }

    private function countRows(string $table, string $column, string $value): int
    {
        $count = $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s = :value', $table, $column),
            ['value' => $value],
        );

        return is_numeric($count) ? (int) $count : 0;
    }
}
