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

namespace App\Tests\Functional\Receipt;

use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceEventEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class ReceiptWebUiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->client->disableReboot();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service is invalid.');
        }
        $this->em = $em;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testUserCanEditReceiptLinesFromUi(): void
    {
        $email = 'receipt.ui.editor@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Edit Station');
        $station->setStreetName('1 Edit Street');
        $station->setPostalCode('75011');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-03 11:00:00'));
        $receipt->setTotalCents(1800);
        $receipt->setVatAmountCents(300);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(10000);
        $line->setUnitPriceDeciCentsPerLiter(1800);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
        $this->em->flush();

        $receiptId = $receipt->getId()->toRfc4122();
        $this->loginWithUiForm($email, $password);

        $editPage = $this->request('GET', '/ui/receipts/'.$receiptId.'/edit');
        self::assertSame(Response::HTTP_OK, $editPage->getStatusCode());
        $csrf = $this->extractFormCsrf((string) $editPage->getContent());

        $editResponse = $this->request(
            'POST',
            '/ui/receipts/'.$receiptId.'/edit',
            [
                '_token' => $csrf,
                'lines' => [
                    [
                        'fuelType' => 'sp95',
                        'quantityLiters' => '12.000',
                        'unitPriceEurosPerLiter' => '1.750',
                        'vatRatePercent' => '20',
                    ],
                ],
            ],
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $editResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ReceiptEntity::class, $receiptId);
        self::assertInstanceOf(ReceiptEntity::class, $updated);
        $updatedLines = $updated->getLines()->toArray();
        self::assertCount(1, $updatedLines);
        $updatedLine = $updatedLines[0];
        self::assertInstanceOf(ReceiptLineEntity::class, $updatedLine);
        self::assertSame('sp95', $updatedLine->getFuelType());
        self::assertSame(12000, $updatedLine->getQuantityMilliLiters());
        self::assertSame(1750, $updatedLine->getUnitPriceDeciCentsPerLiter());
    }

    public function testUserCanEditReceiptMetadataFromUi(): void
    {
        $email = 'receipt.ui.metadata@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicleA = new VehicleEntity();
        $vehicleA->setId(Uuid::v7());
        $vehicleA->setName('Metadata Car A');
        $vehicleA->setPlateNumber('MD-100-AA');
        $vehicleA->setOwner($owner);
        $vehicleA->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicleA->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicleA);

        $vehicleB = new VehicleEntity();
        $vehicleB->setId(Uuid::v7());
        $vehicleB->setName('Metadata Car B');
        $vehicleB->setPlateNumber('MD-200-BB');
        $vehicleB->setOwner($owner);
        $vehicleB->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicleB->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicleB);

        $stationA = new StationEntity();
        $stationA->setId(Uuid::v7());
        $stationA->setName('Station A');
        $stationA->setStreetName('1 Main St');
        $stationA->setPostalCode('75001');
        $stationA->setCity('Paris');
        $this->em->persist($stationA);

        $stationB = new StationEntity();
        $stationB->setId(Uuid::v7());
        $stationB->setName('Station B');
        $stationB->setStreetName('2 Side St');
        $stationB->setPostalCode('69001');
        $stationB->setCity('Lyon');
        $this->em->persist($stationB);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setVehicle($vehicleA);
        $receipt->setStation($stationA);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-03 11:00:00'));
        $receipt->setOdometerKilometers(120000);
        $receipt->setTotalCents(1800);
        $receipt->setVatAmountCents(300);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(10000);
        $line->setUnitPriceDeciCentsPerLiter(1800);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
        $this->em->flush();

        $receiptId = $receipt->getId()->toRfc4122();
        $this->loginWithUiForm($email, $password);

        $editPage = $this->request('GET', '/ui/receipts/'.$receiptId.'/edit-metadata');
        self::assertSame(Response::HTTP_OK, $editPage->getStatusCode());
        $content = (string) $editPage->getContent();
        self::assertStringContainsString('Edit receipt details', $content);
        self::assertStringContainsString('option value="'.$vehicleA->getId()->toRfc4122().'" selected', $content);
        self::assertStringContainsString('option value="'.$stationA->getId()->toRfc4122().'" selected', $content);
        $csrf = $this->extractFormCsrf($content);

        $editResponse = $this->request(
            'POST',
            '/ui/receipts/'.$receiptId.'/edit-metadata',
            [
                '_token' => $csrf,
                'issuedAt' => '2026-03-05T12:45',
                'vehicleId' => $vehicleB->getId()->toRfc4122(),
                'stationId' => $stationB->getId()->toRfc4122(),
                'odometerKilometers' => '121500',
            ],
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $editResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ReceiptEntity::class, $receiptId);
        self::assertInstanceOf(ReceiptEntity::class, $updated);
        self::assertSame($vehicleB->getId()->toRfc4122(), $updated->getVehicle()?->getId()->toRfc4122());
        self::assertSame($stationB->getId()->toRfc4122(), $updated->getStation()?->getId()->toRfc4122());
        self::assertSame(121500, $updated->getOdometerKilometers());
        self::assertSame('2026-03-05 12:45', $updated->getIssuedAt()->format('Y-m-d H:i'));
    }

    public function testReceiptMetadataEditDoesNotExposeForeignVehicles(): void
    {
        $ownerEmail = 'receipt.ui.metadata.owner@example.com';
        $password = 'test1234';
        $owner = $this->createUser($ownerEmail, $password, ['ROLE_USER']);

        $ownerVehicle = new VehicleEntity();
        $ownerVehicle->setId(Uuid::v7());
        $ownerVehicle->setName('Owner Car');
        $ownerVehicle->setPlateNumber('OW-100-AA');
        $ownerVehicle->setOwner($owner);
        $ownerVehicle->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $ownerVehicle->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($ownerVehicle);

        $foreignOwner = $this->createUser('receipt.ui.metadata.foreign@example.com', 'test1234', ['ROLE_USER']);
        $foreignVehicle = new VehicleEntity();
        $foreignVehicle->setId(Uuid::v7());
        $foreignVehicle->setName('Foreign Car');
        $foreignVehicle->setPlateNumber('FR-200-BB');
        $foreignVehicle->setOwner($foreignOwner);
        $foreignVehicle->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $foreignVehicle->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($foreignVehicle);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Owner Station');
        $station->setStreetName('1 Owner Street');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setVehicle($ownerVehicle);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-03 11:00:00'));
        $receipt->setTotalCents(1800);
        $receipt->setVatAmountCents(300);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(10000);
        $line->setUnitPriceDeciCentsPerLiter(1800);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($ownerEmail, $password);

        $editPage = $this->request('GET', '/ui/receipts/'.$receipt->getId()->toRfc4122().'/edit-metadata');
        self::assertSame(Response::HTTP_OK, $editPage->getStatusCode());
        $content = (string) $editPage->getContent();
        self::assertStringContainsString('Owner Car', $content);
        self::assertStringNotContainsString('Foreign Car', $content);
    }

    public function testUserCanCreateReceiptFromHumanFriendlyUnits(): void
    {
        $email = 'receipt.ui.create@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Create Car');
        $vehicle->setPlateNumber('CR-100-AA');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $createPage = $this->request('GET', '/ui/receipts/new?vehicle_id='.$vehicle->getId()->toRfc4122());
        self::assertSame(Response::HTTP_OK, $createPage->getStatusCode());
        $createContent = (string) $createPage->getContent();
        self::assertStringContainsString('Quantity (L)', $createContent);
        self::assertStringContainsString('Unit price (€/L)', $createContent);
        self::assertStringContainsString('option value="'.$vehicle->getId()->toRfc4122().'" selected', $createContent);
        $csrf = $this->extractFormCsrf($createContent);

        $createResponse = $this->request('POST', '/ui/receipts/new', [
            '_token' => $csrf,
            'issuedAt' => '2026-03-05T14:20',
            'vehicleId' => $vehicle->getId()->toRfc4122(),
            'fuelType' => 'diesel',
            'quantityLiters' => '40,40',
            'unitPriceEurosPerLiter' => '1,769',
            'vatRatePercent' => '20',
            'stationName' => 'PETRO EST',
            'stationStreetName' => 'LECLERC SEZANNE HYPER',
            'stationPostalCode' => '51120',
            'stationCity' => 'SEZANNE',
            'latitudeMicroDegrees' => '',
            'longitudeMicroDegrees' => '',
            'odometerKilometers' => '120450',
        ]);
        self::assertSame(Response::HTTP_SEE_OTHER, $createResponse->getStatusCode());

        $this->em->clear();
        $receipts = $this->em->getRepository(ReceiptEntity::class)->findAll();
        self::assertCount(1, $receipts);
        $receipt = $receipts[0];
        self::assertInstanceOf(ReceiptEntity::class, $receipt);
        self::assertSame($owner->getId()->toRfc4122(), $receipt->getOwner()?->getId()->toRfc4122());
        self::assertSame($vehicle->getId()->toRfc4122(), $receipt->getVehicle()?->getId()->toRfc4122());
        self::assertSame(120450, $receipt->getOdometerKilometers());
        $lines = $receipt->getLines()->toArray();
        self::assertCount(1, $lines);
        $line = $lines[0];
        self::assertInstanceOf(ReceiptLineEntity::class, $line);
        self::assertSame(40400, $line->getQuantityMilliLiters());
        self::assertSame(1769, $line->getUnitPriceDeciCentsPerLiter());
    }

    public function testReceiptIndexRowsUseSharedRowLinkNavigation(): void
    {
        $email = 'receipt.ui.list@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('List Station');
        $station->setStreetName('9 View Street');
        $station->setPostalCode('75012');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-04 09:30:00'));
        $receipt->setTotalCents(2200);
        $receipt->setVatAmountCents(367);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(12000);
        $line->setUnitPriceDeciCentsPerLiter(1833);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $response = $this->request('GET', '/ui/receipts');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringContainsString('data-controller="row-link"', $content);
        self::assertStringContainsString('data-row-link-url-value="/ui/receipts/'.$receipt->getId()->toRfc4122().'?', $content);
        self::assertMatchesRegularExpression('/data-row-link-url-value="\\/ui\\/receipts\\/'.$receipt->getId()->toRfc4122().'\\?[^"]*return_to=/', $content);
    }

    public function testReceiptIndexCanFilterByVehicle(): void
    {
        $email = 'receipt.ui.vehicle.filter@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicleA = new VehicleEntity();
        $vehicleA->setId(Uuid::v7());
        $vehicleA->setName('Vehicle A');
        $vehicleA->setPlateNumber('AA-100-AA');
        $vehicleA->setOwner($owner);
        $vehicleA->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicleA->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicleA);

        $vehicleB = new VehicleEntity();
        $vehicleB->setId(Uuid::v7());
        $vehicleB->setName('Vehicle B');
        $vehicleB->setPlateNumber('BB-200-BB');
        $vehicleB->setOwner($owner);
        $vehicleB->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicleB->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicleB);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Vehicle Filter Station');
        $station->setStreetName('2 Filter Avenue');
        $station->setPostalCode('75014');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receiptA = new ReceiptEntity();
        $receiptA->setId(Uuid::v7());
        $receiptA->setOwner($owner);
        $receiptA->setVehicle($vehicleA);
        $receiptA->setStation($station);
        $receiptA->setIssuedAt(new DateTimeImmutable('2026-03-14 08:00:00'));
        $receiptA->setTotalCents(2100);
        $receiptA->setVatAmountCents(350);
        $lineA = new ReceiptLineEntity();
        $lineA->setId(Uuid::v7());
        $lineA->setFuelType('diesel');
        $lineA->setQuantityMilliLiters(10000);
        $lineA->setUnitPriceDeciCentsPerLiter(2100);
        $lineA->setVatRatePercent(20);
        $receiptA->addLine($lineA);
        $this->em->persist($receiptA);

        $receiptB = new ReceiptEntity();
        $receiptB->setId(Uuid::v7());
        $receiptB->setOwner($owner);
        $receiptB->setVehicle($vehicleB);
        $receiptB->setStation($station);
        $receiptB->setIssuedAt(new DateTimeImmutable('2026-03-15 09:00:00'));
        $receiptB->setTotalCents(2300);
        $receiptB->setVatAmountCents(383);
        $lineB = new ReceiptLineEntity();
        $lineB->setId(Uuid::v7());
        $lineB->setFuelType('sp95');
        $lineB->setQuantityMilliLiters(11000);
        $lineB->setUnitPriceDeciCentsPerLiter(2090);
        $lineB->setVatRatePercent(20);
        $receiptB->addLine($lineB);
        $this->em->persist($receiptB);

        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $response = $this->request('GET', '/ui/receipts?vehicle_id='.$vehicleA->getId()->toRfc4122());
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringContainsString('Vehicle A (AA-100-AA)', $content);
        self::assertStringContainsString($receiptA->getId()->toRfc4122(), $content);
        self::assertStringNotContainsString($receiptB->getId()->toRfc4122(), $content);
    }

    public function testReceiptIndexShowsVehicleScopedShortcutsAndPrefilledCreateLink(): void
    {
        $email = 'receipt.ui.list.shortcuts@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Shortcut Car');
        $vehicle->setPlateNumber('SC-100-AA');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $vehicleId = $vehicle->getId()->toRfc4122();
        $response = $this->request('GET', '/ui/receipts?vehicle_id='.$vehicleId);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringContainsString('Vehicle shortcuts', $content);
        self::assertStringContainsString('Shortcut Car (SC-100-AA)', $content);
        self::assertStringContainsString('/ui/vehicles/'.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/analytics?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance/events/new?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/receipts/new?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('Vehicle:</strong> Shortcut Car (SC-100-AA)', $content);
        self::assertStringContainsString('Last 30 days', $content);
        self::assertStringContainsString('This month', $content);
    }

    public function testReceiptIndexShowsStationScopedShortcutsAndPrefilledCreateLink(): void
    {
        $email = 'receipt.ui.station.shortcuts@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Shortcut Station');
        $station->setStreetName('12 Route Nord');
        $station->setPostalCode('59000');
        $station->setCity('Lille');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-15 09:00:00'));
        $receipt->setTotalCents(2300);
        $receipt->setVatAmountCents(383);
        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('sp95');
        $line->setQuantityMilliLiters(11000);
        $line->setUnitPriceDeciCentsPerLiter(2090);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);
        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $stationId = $station->getId()->toRfc4122();
        $response = $this->request('GET', '/ui/receipts?station_id='.$stationId);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringContainsString('Station shortcuts', $content);
        self::assertStringContainsString('Shortcut Station - 12 Route Nord, 59000 Lille', $content);
        self::assertStringContainsString('/ui/stations/'.$stationId, $content);
        self::assertStringContainsString('/ui/analytics?station_id='.$stationId, $content);
        self::assertStringContainsString('/ui/stations/'.$stationId.'/edit', $content);
        self::assertStringContainsString('redirect=', $content);
        self::assertStringContainsString('station_id', $content);
        self::assertStringContainsString('/ui/receipts/new?station_id='.$stationId, $content);
        self::assertStringContainsString('Station:</strong> Shortcut Station - 12 Route Nord, 59000 Lille', $content);

        $createPage = $this->request('GET', '/ui/receipts/new?station_id='.$stationId);
        self::assertSame(Response::HTTP_OK, $createPage->getStatusCode());
        $createContent = (string) $createPage->getContent();
        self::assertStringContainsString('name="stationName" value="Shortcut Station"', $createContent);
        self::assertStringContainsString('name="stationStreetName" value="12 Route Nord"', $createContent);
        self::assertStringContainsString('name="stationPostalCode" value="59000"', $createContent);
        self::assertStringContainsString('name="stationCity" value="Lille"', $createContent);
    }

    public function testReceiptDetailKeepsReturnToContextFromFilteredList(): void
    {
        $email = 'receipt.ui.context@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Context Station');
        $station->setStreetName('12 Filter Street');
        $station->setPostalCode('75013');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-12 08:00:00'));
        $receipt->setTotalCents(2150);
        $receipt->setVatAmountCents(358);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(11000);
        $line->setUnitPriceDeciCentsPerLiter(1955);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $returnTo = '/ui/receipts?issued_from=2026-03-01&issued_to=2026-03-31&sort_by=total&sort_direction=asc';
        $showResponse = $this->request('GET', '/ui/receipts/'.$receipt->getId()->toRfc4122().'?return_to='.rawurlencode($returnTo));
        self::assertSame(Response::HTTP_OK, $showResponse->getStatusCode());
        $content = (string) $showResponse->getContent();
        $escapedReturnTo = htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        self::assertStringContainsString('href="'.$escapedReturnTo.'"', $content);
        self::assertStringContainsString('name="_redirect" value="'.$escapedReturnTo.'"', $content);
    }

    public function testReceiptDetailShowsVehicleContextAndNextActions(): void
    {
        $email = 'receipt.ui.detail.context@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Context Car');
        $vehicle->setPlateNumber('CC-300-CC');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-10 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-10 10:00:00'));
        $this->em->persist($vehicle);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Context Detail Station');
        $station->setStreetName('20 Detail Avenue');
        $station->setPostalCode('75015');
        $station->setCity('Paris');
        $this->em->persist($station);

        $event = new MaintenanceEventEntity();
        $event->setId(Uuid::v7());
        $event->setOwner($owner);
        $event->setVehicle($vehicle);
        $event->setEventType(MaintenanceEventType::SERVICE);
        $event->setOccurredAt(new DateTimeImmutable('2026-03-09 09:00:00'));
        $event->setDescription('Recent maintenance');
        $event->setOdometerKilometers(124000);
        $event->setTotalCostCents(15990);
        $event->setCurrencyCode('EUR');
        $event->setCreatedAt(new DateTimeImmutable('2026-03-09 09:00:00'));
        $event->setUpdatedAt(new DateTimeImmutable('2026-03-09 09:00:00'));
        $this->em->persist($event);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setVehicle($vehicle);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-12 18:40:00'));
        $receipt->setOdometerKilometers(124650);
        $receipt->setTotalCents(2500);
        $receipt->setVatAmountCents(417);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(13000);
        $line->setUnitPriceDeciCentsPerLiter(1923);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $response = $this->request('GET', '/ui/receipts/'.$receipt->getId()->toRfc4122());
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();
        $vehicleId = $vehicle->getId()->toRfc4122();
        self::assertStringContainsString('Vehicle context', $content);
        self::assertStringContainsString('Context Car (CC-300-CC)', $content);
        self::assertStringContainsString('/ui/vehicles/'.$vehicleId, $content);
        self::assertStringContainsString('/ui/receipts?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/analytics?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance/events/new?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance/events/'.$event->getId()->toRfc4122().'/edit', $content);
        self::assertStringContainsString('Recent maintenance', $content);
        self::assertStringContainsString('Distance since last maintenance:</strong> 650 km', $content);
        self::assertStringContainsString('Quick corrections', $content);
        self::assertStringContainsString('Adjust fuel lines', $content);
        self::assertStringContainsString('Edit last maintenance', $content);
        self::assertStringContainsString('Edit station', $content);
    }

    public function testReceiptDetailHighlightsMissingContextCorrections(): void
    {
        $email = 'receipt.ui.detail.quickfix@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-18 08:30:00'));
        $receipt->setTotalCents(4500);
        $receipt->setVatAmountCents(750);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(25000);
        $line->setUnitPriceDeciCentsPerLiter(1800);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $response = $this->request('GET', '/ui/receipts/'.$receipt->getId()->toRfc4122());
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();

        self::assertStringContainsString('Quick corrections', $content);
        self::assertStringContainsString('Link vehicle', $content);
        self::assertStringContainsString('Link station', $content);
        self::assertStringContainsString('Add odometer', $content);
        self::assertStringContainsString('Adjust fuel lines', $content);
        self::assertStringNotContainsString('Edit last maintenance', $content);
    }

    /**
     * @param array<string, string|int|float|bool|array<int, array<string, string>>|null> $parameters
     * @param array<string, string>                                                       $server
     * @param array<string, string>                                                       $cookies
     */
    private function request(string $method, string $uri, array $parameters = [], array $server = [], array $cookies = []): Response
    {
        $this->client->request($method, $uri, $parameters, [], $server);

        return $this->client->getResponse();
    }

    /** @return array<string, string> */
    private function loginWithUiForm(string $email, string $password): array
    {
        $loginPageResponse = $this->request('GET', '/ui/login');
        self::assertSame(Response::HTTP_OK, $loginPageResponse->getStatusCode());

        $content = (string) $loginPageResponse->getContent();
        preg_match('/name="_csrf_token" value="([^"]+)"/', $content, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);

        $loginResponse = $this->request(
            'POST',
            '/ui/login',
            [
                'email' => $email,
                'password' => $password,
                '_csrf_token' => $csrfToken,
            ],
        );

        self::assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());

        return [];
    }

    private function extractFormCsrf(string $content): string
    {
        self::assertMatchesRegularExpression('/name="_token" value="([^"]+)"/', $content);
        preg_match('/name="_token" value="([^"]+)"/', $content, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);
        self::assertNotSame('', $csrfToken);

        return $csrfToken;
    }

    /** @param list<string> $roles */
    private function createUser(string $email, string $password, array $roles): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->em->persist($user);

        return $user;
    }
}
