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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class SeedAnalyticsDemoDataCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $connection;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;
        $this->connection = $em->getConnection();
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service not found.');
        }
        $this->passwordHasher = $passwordHasher;

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
        self::assertContains('ROLE_ANALYTICS_DEMO', $user->getRoles());

        $secondRunExitCode = $tester->execute([
            '--email' => 'demo.analytics@test.local',
            '--password' => 'secret-demo-password',
        ]);

        self::assertSame(0, $secondRunExitCode);
        self::assertSame(8, $this->countRows('receipts', 'owner_id', $user->getId()->toRfc4122()));
        self::assertSame(3, $this->countRows('maintenance_events', 'owner_id', $user->getId()->toRfc4122()));
        self::assertSame(2, $this->countRows('vehicles', 'owner_id', $user->getId()->toRfc4122()));
    }

    public function testCommandRejectsExistingNonDemoUserEmail(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail('real.user@test.local');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'real-password'));
        $this->em->persist($user);
        $this->em->flush();

        $tester = $this->commandTester();
        $exitCode = $tester->execute([
            '--email' => 'real.user@test.local',
            '--password' => 'secret-demo-password',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Refusing to reuse existing non-demo user', $tester->getDisplay());
        self::assertSame(0, $this->countRows('receipts', 'owner_id', $user->getId()->toRfc4122()));

        $this->em->clear();
        $reloadedUser = $this->em->find(UserEntity::class, $user->getId()->toRfc4122());
        self::assertInstanceOf(UserEntity::class, $reloadedUser);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $reloadedUser->getRoles());
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
