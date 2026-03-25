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

namespace App\Analytics\Infrastructure\Console;

use App\Analytics\Application\Aggregation\ReceiptAnalyticsProjectionRefresher;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceEventEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Domain\Enum\GeocodingStatus;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:analytics:demo-seed', description: 'Seed a dedicated demo user with analytics-friendly sample data')]
final class SeedAnalyticsDemoDataCommand extends Command
{
    private const string DEFAULT_EMAIL = 'analytics.demo@example.com';
    private const string DEFAULT_PASSWORD = 'demo1234';
    private const string DEMO_ROLE = 'ROLE_ANALYTICS_DEMO';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ReceiptAnalyticsProjectionRefresher $projectionRefresher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Demo account email', self::DEFAULT_EMAIL)
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Demo account password', self::DEFAULT_PASSWORD);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $emailOption = $input->getOption('email');
        $passwordOption = $input->getOption('password');
        if (!is_string($emailOption) || !is_string($passwordOption)) {
            $output->writeln('<error>Invalid command options.</error>');

            return Command::INVALID;
        }

        $email = mb_strtolower(trim($emailOption));
        $password = $passwordOption;
        if ('' === $email || '' === $password) {
            $output->writeln('<error>Email and password cannot be empty.</error>');

            return Command::INVALID;
        }

        try {
            $user = $this->findOrCreateUser($email, $password);
        } catch (RuntimeException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $this->em->flush();
        $user = $this->resetDemoDataForUser($user, $password);

        [$familyCar, $cityCar] = $this->createVehicles($user);
        [$stationParis, $stationLyon, $stationBordeaux] = $this->createStations();

        $this->createReceipt($user, $familyCar, $stationParis, new DateTimeImmutable('first day of -5 months 08:15'), 48_210, [
            ['diesel', 31_500, 1530, 20],
        ]);
        $this->createReceipt($user, $cityCar, $stationLyon, new DateTimeImmutable('first day of -4 months 18:20'), 18_400, [
            ['sp95', 24_100, 1680, 20],
        ]);
        $this->createReceipt($user, $familyCar, $stationParis, new DateTimeImmutable('first day of -3 months 07:45'), 53_600, [
            ['diesel', 34_200, 1595, 20],
        ]);
        $this->createReceipt($user, $cityCar, $stationBordeaux, new DateTimeImmutable('first day of -3 months 19:05'), 19_150, [
            ['sp98', 26_000, 1820, 20],
        ]);
        $this->createReceipt($user, $familyCar, $stationLyon, new DateTimeImmutable('first day of -2 months 08:05'), 58_200, [
            ['diesel', 35_400, 1650, 20],
        ]);
        $this->createReceipt($user, $cityCar, $stationParis, new DateTimeImmutable('first day of -1 month 18:10'), 20_050, [
            ['sp95', 23_600, 1755, 20],
        ]);
        $this->createReceipt($user, $familyCar, $stationBordeaux, new DateTimeImmutable('first day of this month 07:55'), 63_400, [
            ['diesel', 36_100, 1715, 20],
        ]);
        $this->createReceipt($user, $cityCar, $stationLyon, new DateTimeImmutable('first day of this month 19:30'), 21_000, [
            ['sp98', 24_800, 1865, 20],
        ]);

        $this->createMaintenanceEvent($user, $familyCar, new DateTimeImmutable('first day of -4 months 09:00'), 42_000, 65_000, MaintenanceEventType::SERVICE, 'Scheduled service');
        $this->createMaintenanceEvent($user, $cityCar, new DateTimeImmutable('first day of -2 months 14:00'), 18_500, 40_500, MaintenanceEventType::INSPECTION, 'Technical inspection');
        $this->createMaintenanceEvent($user, $familyCar, new DateTimeImmutable('first day of this month 10:30'), 27_000, 84_000, MaintenanceEventType::REPAIR, 'Brake pads replacement');

        $this->em->flush();

        $report = $this->projectionRefresher->refresh();

        $output->writeln(sprintf('<info>Analytics demo data ready for %s</info>', $email));
        $output->writeln(sprintf('Password: %s', $password));
        $output->writeln('Receipts: 8');
        $output->writeln('Maintenance events: 3');
        $output->writeln(sprintf('Projection rows materialized: %d', $report->rowsMaterialized));

        return Command::SUCCESS;
    }

    private function findOrCreateUser(string $email, string $password): UserEntity
    {
        $user = $this->em->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        if ($user instanceof UserEntity) {
            if (!$this->isManagedDemoUser($user)) {
                throw new RuntimeException(sprintf('Refusing to reuse existing non-demo user "%s". Choose a dedicated demo email instead.', $email));
            }

            $user->setRoles(['ROLE_USER', self::DEMO_ROLE]);
            $user->setIsActive(true);

            return $user;
        }

        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER', self::DEMO_ROLE]);
        $user->setIsActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);

        return $user;
    }

    private function isManagedDemoUser(UserEntity $user): bool
    {
        return in_array(self::DEMO_ROLE, $user->getRoles(), true);
    }

    private function resetDemoDataForUser(UserEntity $user, string $password): UserEntity
    {
        $ownerId = $user->getId()->toRfc4122();
        $connection = $this->em->getConnection();

        $connection->transactional(function (Connection $connection) use ($ownerId): void {
            $connection->executeStatement(
                'DELETE FROM receipt_lines WHERE receipt_id IN (SELECT id FROM receipts WHERE owner_id = :ownerId)',
                ['ownerId' => $ownerId],
            );
            $connection->executeStatement('DELETE FROM receipts WHERE owner_id = :ownerId', ['ownerId' => $ownerId]);
            $connection->executeStatement('DELETE FROM maintenance_events WHERE owner_id = :ownerId', ['ownerId' => $ownerId]);
            $connection->executeStatement('DELETE FROM maintenance_reminders WHERE owner_id = :ownerId', ['ownerId' => $ownerId]);
            $connection->executeStatement('DELETE FROM maintenance_reminder_rules WHERE owner_id = :ownerId', ['ownerId' => $ownerId]);
            $connection->executeStatement('DELETE FROM import_jobs WHERE owner_id = :ownerId', ['ownerId' => $ownerId]);
            $connection->executeStatement('DELETE FROM vehicles WHERE owner_id = :ownerId', ['ownerId' => $ownerId]);
        });

        $this->em->clear();

        $managedUser = $this->em->find(UserEntity::class, $ownerId);
        if ($managedUser instanceof UserEntity) {
            $managedUser->setPassword($this->passwordHasher->hashPassword($managedUser, $password));

            return $managedUser;
        }

        throw new RuntimeException('Demo user could not be reloaded after cleanup.');
    }

    /** @return array{VehicleEntity, VehicleEntity} */
    private function createVehicles(UserEntity $user): array
    {
        $createdAt = new DateTimeImmutable('now');

        $familyCar = new VehicleEntity();
        $familyCar->setId(Uuid::v7());
        $familyCar->setOwner($user);
        $familyCar->setName('Demo Family Car');
        $familyCar->setPlateNumber('DEMO-001-AA');
        $familyCar->setCreatedAt($createdAt);
        $familyCar->setUpdatedAt($createdAt);
        $this->em->persist($familyCar);

        $cityCar = new VehicleEntity();
        $cityCar->setId(Uuid::v7());
        $cityCar->setOwner($user);
        $cityCar->setName('Demo City Car');
        $cityCar->setPlateNumber('DEMO-002-BB');
        $cityCar->setCreatedAt($createdAt);
        $cityCar->setUpdatedAt($createdAt);
        $this->em->persist($cityCar);

        return [$familyCar, $cityCar];
    }

    /** @return array{StationEntity, StationEntity, StationEntity} */
    private function createStations(): array
    {
        return [
            $this->findOrCreateStation('Demo Station Paris', '12 Avenue de la Republique', '75011', 'Paris', 48_867_100, 2_376_200),
            $this->findOrCreateStation('Demo Station Lyon', '5 Rue de la Part-Dieu', '69003', 'Lyon', 45_760_000, 4_858_000),
            $this->findOrCreateStation('Demo Station Bordeaux', '18 Quai des Chartrons', '33000', 'Bordeaux', 44_847_800, -579_000),
        ];
    }

    private function findOrCreateStation(
        string $name,
        string $streetName,
        string $postalCode,
        string $city,
        int $latitudeMicroDegrees,
        int $longitudeMicroDegrees,
    ): StationEntity {
        $station = $this->em->getRepository(StationEntity::class)->findOneBy([
            'name' => $name,
            'streetName' => $streetName,
            'postalCode' => $postalCode,
            'city' => $city,
        ]);

        if (!$station instanceof StationEntity) {
            $station = new StationEntity();
            $station->setId(Uuid::v7());
            $station->setName($name);
            $station->setStreetName($streetName);
            $station->setPostalCode($postalCode);
            $station->setCity($city);
            $this->em->persist($station);
        }

        $geocodedAt = new DateTimeImmutable('now');
        $station->setLatitudeMicroDegrees($latitudeMicroDegrees);
        $station->setLongitudeMicroDegrees($longitudeMicroDegrees);
        $station->setGeocodingStatus(GeocodingStatus::SUCCESS);
        $station->setGeocodingRequestedAt($geocodedAt);
        $station->setGeocodedAt($geocodedAt);
        $station->setGeocodingFailedAt(null);
        $station->setGeocodingLastError(null);

        return $station;
    }

    /**
     * @param list<array{0:string,1:int,2:int,3:int}> $lines
     */
    private function createReceipt(
        UserEntity $owner,
        VehicleEntity $vehicle,
        StationEntity $station,
        DateTimeImmutable $issuedAt,
        int $odometerKilometers,
        array $lines,
    ): void {
        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setVehicle($vehicle);
        $receipt->setStation($station);
        $receipt->setIssuedAt($issuedAt);
        $receipt->setOdometerKilometers($odometerKilometers);

        $totalCents = 0;
        $vatAmountCents = 0;

        foreach ($lines as [$fuelType, $quantityMilliLiters, $unitPriceDeciCentsPerLiter, $vatRatePercent]) {
            $line = new ReceiptLineEntity();
            $line->setId(Uuid::v7());
            $line->setFuelType($fuelType);
            $line->setQuantityMilliLiters($quantityMilliLiters);
            $line->setUnitPriceDeciCentsPerLiter($unitPriceDeciCentsPerLiter);
            $line->setVatRatePercent($vatRatePercent);
            $receipt->addLine($line);

            $lineTotal = (int) round(($unitPriceDeciCentsPerLiter * $quantityMilliLiters) / 10000, 0, PHP_ROUND_HALF_UP);
            $totalCents += $lineTotal;
            $vatAmountCents += (int) round($lineTotal * $vatRatePercent / (100 + $vatRatePercent), 0, PHP_ROUND_HALF_UP);
        }

        $receipt->setTotalCents($totalCents);
        $receipt->setVatAmountCents($vatAmountCents);
        $this->em->persist($receipt);
    }

    private function createMaintenanceEvent(
        UserEntity $owner,
        VehicleEntity $vehicle,
        DateTimeImmutable $occurredAt,
        int $totalCostCents,
        int $odometerKilometers,
        MaintenanceEventType $eventType,
        string $description,
    ): void {
        $event = new MaintenanceEventEntity();
        $event->setId(Uuid::v7());
        $event->setOwner($owner);
        $event->setVehicle($vehicle);
        $event->setEventType($eventType);
        $event->setOccurredAt($occurredAt);
        $event->setDescription($description);
        $event->setOdometerKilometers($odometerKilometers);
        $event->setTotalCostCents($totalCostCents);
        $event->setCurrencyCode('EUR');
        $event->setCreatedAt($occurredAt);
        $event->setUpdatedAt($occurredAt);
        $this->em->persist($event);
    }
}
