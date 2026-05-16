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

namespace App\Tests\Functional\Station;

use App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity\PublicFuelStationEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class StationWebUiTest extends WebTestCase
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

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE favorite_stations, public_fuel_station_sync_runs, public_fuel_stations, maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testStationDetailActsAsWorkflowHub(): void
    {
        $email = 'station.ui.show@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Hub Station');
        $station->setStreetName('5 Place Centrale');
        $station->setPostalCode('33000');
        $station->setCity('Bordeaux');
        $station->setLatitudeMicroDegrees(44569000);
        $station->setLongitudeMicroDegrees(-579000);
        $this->em->persist($station);

        $publicStation = new PublicFuelStationEntity();
        $publicStation->setSourceId('public-hub-station');
        $publicStation->setLatitudeMicroDegrees(44569010);
        $publicStation->setLongitudeMicroDegrees(-579010);
        $publicStation->setAddress('5 PLACE CENTRALE');
        $publicStation->setPostalCode('33000');
        $publicStation->setCity('BORDEAUX');
        $publicStation->setAutomate24(true);
        $publicStation->setServices(['Station de gonflage']);
        $publicStation->setFuels([
            'gazole' => [
                'available' => true,
                'priceMilliEurosPerLiter' => 1789,
                'priceUpdatedAt' => '2026-04-28T09:15:00+02:00',
                'ruptureType' => null,
                'ruptureStartedAt' => null,
            ],
        ]);
        $publicStation->setSourceUpdatedAt(new DateTimeImmutable('2026-04-28 09:15:00'));
        $publicStation->setImportedAt(new DateTimeImmutable('2026-04-28 09:20:00'));
        $this->em->persist($publicStation);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-16 10:15:00'));
        $receipt->setOdometerKilometers(145200);
        $receipt->setTotalCents(5400);
        $receipt->setVatAmountCents(900);
        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(30000);
        $line->setUnitPriceDeciCentsPerLiter(1800);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);
        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $stationId = $station->getId()->toRfc4122();
        $response = $this->request('GET', '/ui/stations/'.$stationId.'?return_to='.rawurlencode('/ui/receipts?station_id='.$stationId));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringContainsString('Hub Station', $content);
        self::assertStringContainsString('Reçus suivis', $content);
        self::assertStringContainsString('Derniers reçus', $content);
        self::assertStringContainsString('/ui/receipts?station_id='.$stationId, $content);
        self::assertStringContainsString('/ui/analytics?station_id='.$stationId, $content);
        self::assertStringContainsString('/ui/receipts/new?station_id='.$stationId, $content);
        self::assertStringContainsString('/ui/receipts/'.$receipt->getId()->toRfc4122(), $content);
        self::assertStringContainsString('145200 km', $content);
        self::assertStringContainsString('Stations publiques candidates', $content);
        self::assertStringContainsString('public-hub-station', $content);
        self::assertStringContainsString('1.789 EUR/L', $content);
    }

    public function testStationDetailRequiresAccessibleStationForFrontUsers(): void
    {
        $email = 'station.ui.empty@example.com';
        $password = 'test1234';
        $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Empty Station');
        $station->setStreetName('8 Rue Vide');
        $station->setPostalCode('44000');
        $station->setCity('Nantes');
        $this->em->persist($station);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $stationId = $station->getId()->toRfc4122();
        $response = $this->request('GET', '/ui/stations/'.$stationId);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testStationListActsAsProductiveFrontIndex(): void
    {
        $email = 'station.ui.index@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $stationA = new StationEntity();
        $stationA->setId(Uuid::v7());
        $stationA->setName('North Station');
        $stationA->setStreetName('12 Route Nord');
        $stationA->setPostalCode('59000');
        $stationA->setCity('Lille');
        $this->em->persist($stationA);

        $stationB = new StationEntity();
        $stationB->setId(Uuid::v7());
        $stationB->setName('Unused Station');
        $stationB->setStreetName('4 Avenue Sud');
        $stationB->setPostalCode('31000');
        $stationB->setCity('Toulouse');
        $this->em->persist($stationB);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($stationA);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-22 09:45:00'));
        $receipt->setOdometerKilometers(158900);
        $receipt->setTotalCents(6200);
        $receipt->setVatAmountCents(1033);
        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(34000);
        $line->setUnitPriceDeciCentsPerLiter(1824);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);
        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $response = $this->request('GET', '/ui/stations');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();

        self::assertStringContainsString('Stations', $content);
        self::assertStringContainsString('North Station', $content);
        self::assertStringNotContainsString('Unused Station', $content);
        self::assertStringNotContainsString('Tracked stations', $content);
        self::assertStringNotContainsString('Accessible stations', $content);
        self::assertStringContainsString('/ui/stations/'.$stationA->getId()->toRfc4122(), $content);
        self::assertStringContainsString('/ui/receipts?station_id='.$stationA->getId()->toRfc4122(), $content);
        self::assertStringContainsString('/ui/analytics?station_id='.$stationA->getId()->toRfc4122(), $content);
        self::assertStringContainsString('/ui/receipts/new?station_id='.$stationA->getId()->toRfc4122(), $content);
        self::assertStringNotContainsString('receipt_form_frame', $content);
        self::assertStringContainsString('158900 km', $content);
    }

    public function testStationListCanToggleFavoriteState(): void
    {
        $email = 'station.ui.favorite.list@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Favorite List Station');
        $station->setStreetName('12 Rue des Favoris');
        $station->setPostalCode('75011');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-04-25 11:45:00'));
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

        $stationId = $station->getId()->toRfc4122();
        $pageResponse = $this->request('GET', '/ui/stations');
        self::assertSame(Response::HTTP_OK, $pageResponse->getStatusCode());
        $csrfToken = $this->extractFavoriteToggleToken((string) $pageResponse->getContent(), $stationId);

        $toggleOnResponse = $this->request('POST', '/ui/stations/'.$stationId.'/toggle-favorite', [
            '_token' => $csrfToken,
            '_redirect' => '/ui/stations',
        ]);
        self::assertSame(Response::HTTP_FOUND, $toggleOnResponse->getStatusCode());
        self::assertSame('/ui/stations', $toggleOnResponse->headers->get('Location'));
        self::assertSame(1, $this->favoriteCount($owner->getId()->toRfc4122(), $stationId));

        $afterToggleOn = $this->request('GET', '/ui/stations');
        self::assertSame(Response::HTTP_OK, $afterToggleOn->getStatusCode());
        $afterToggleOnContent = (string) $afterToggleOn->getContent();
        self::assertStringContainsString('Station ajoutée aux favoris.', $afterToggleOnContent);
        self::assertStringContainsString('Favorite List Station', $afterToggleOnContent);
        self::assertStringContainsString('Favorite</span>', $afterToggleOnContent);

        $csrfToken = $this->extractFavoriteToggleToken($afterToggleOnContent, $stationId);
        $toggleOffResponse = $this->request('POST', '/ui/stations/'.$stationId.'/toggle-favorite', [
            '_token' => $csrfToken,
            '_redirect' => '/ui/stations',
        ]);
        self::assertSame(Response::HTTP_FOUND, $toggleOffResponse->getStatusCode());
        self::assertSame('/ui/stations', $toggleOffResponse->headers->get('Location'));
        self::assertSame(0, $this->favoriteCount($owner->getId()->toRfc4122(), $stationId));

        $afterToggleOff = $this->request('GET', '/ui/stations');
        self::assertSame(Response::HTTP_OK, $afterToggleOff->getStatusCode());
        self::assertStringContainsString('Station retirée des favoris.', (string) $afterToggleOff->getContent());
    }

    public function testStationDetailCanToggleFavoriteState(): void
    {
        $email = 'station.ui.favorite.detail@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Favorite Detail Station');
        $station->setStreetName('44 Rue du Detail');
        $station->setPostalCode('69002');
        $station->setCity('Lyon');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-04-24 09:00:00'));
        $receipt->setTotalCents(5100);
        $receipt->setVatAmountCents(850);
        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(26000);
        $line->setUnitPriceDeciCentsPerLiter(1961);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);
        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $stationId = $station->getId()->toRfc4122();
        $detailPath = '/ui/stations/'.$stationId.'?return_to='.rawurlencode('/ui/stations');
        $detailResponse = $this->request('GET', $detailPath);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Pas encore en favorites', $detailContent);
        $csrfToken = $this->extractFavoriteToggleToken($detailContent, $stationId);

        $toggleResponse = $this->request('POST', '/ui/stations/'.$stationId.'/toggle-favorite', [
            '_token' => $csrfToken,
            '_redirect' => '/ui/stations/'.$stationId.'?return_to=%2Fui%2Fstations',
        ]);
        self::assertSame(Response::HTTP_FOUND, $toggleResponse->getStatusCode());
        self::assertSame('/ui/stations/'.$stationId.'?return_to=%2Fui%2Fstations', $toggleResponse->headers->get('Location'));
        self::assertSame(1, $this->favoriteCount($owner->getId()->toRfc4122(), $stationId));

        $afterToggle = $this->request('GET', $detailPath);
        self::assertSame(Response::HTTP_OK, $afterToggle->getStatusCode());
        $afterToggleContent = (string) $afterToggle->getContent();
        self::assertStringContainsString('Favorite station', $afterToggleContent);
        self::assertStringContainsString('Station ajoutée aux favoris.', $afterToggleContent);
    }

    public function testStationToggleFavoriteRejectsInvalidCsrfAndInaccessibleStation(): void
    {
        $email = 'station.ui.favorite.security@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);
        $otherOwner = $this->createUser('station.ui.favorite.other@example.com', 'test1234', ['ROLE_USER']);

        $visibleStation = new StationEntity();
        $visibleStation->setId(Uuid::v7());
        $visibleStation->setName('Visible Favorite Station');
        $visibleStation->setStreetName('1 Rue Visible');
        $visibleStation->setPostalCode('13001');
        $visibleStation->setCity('Marseille');
        $this->em->persist($visibleStation);

        $foreignStation = new StationEntity();
        $foreignStation->setId(Uuid::v7());
        $foreignStation->setName('Foreign Station');
        $foreignStation->setStreetName('9 Rue Cachee');
        $foreignStation->setPostalCode('06000');
        $foreignStation->setCity('Nice');
        $this->em->persist($foreignStation);

        $visibleReceipt = new ReceiptEntity();
        $visibleReceipt->setId(Uuid::v7());
        $visibleReceipt->setOwner($owner);
        $visibleReceipt->setStation($visibleStation);
        $visibleReceipt->setIssuedAt(new DateTimeImmutable('2026-04-20 08:00:00'));
        $visibleReceipt->setTotalCents(4000);
        $visibleReceipt->setVatAmountCents(666);
        $visibleLine = new ReceiptLineEntity();
        $visibleLine->setId(Uuid::v7());
        $visibleLine->setFuelType('diesel');
        $visibleLine->setQuantityMilliLiters(22000);
        $visibleLine->setUnitPriceDeciCentsPerLiter(1818);
        $visibleLine->setVatRatePercent(20);
        $visibleReceipt->addLine($visibleLine);
        $this->em->persist($visibleReceipt);

        $foreignReceipt = new ReceiptEntity();
        $foreignReceipt->setId(Uuid::v7());
        $foreignReceipt->setOwner($otherOwner);
        $foreignReceipt->setStation($foreignStation);
        $foreignReceipt->setIssuedAt(new DateTimeImmutable('2026-04-21 08:00:00'));
        $foreignReceipt->setTotalCents(4200);
        $foreignReceipt->setVatAmountCents(700);
        $foreignLine = new ReceiptLineEntity();
        $foreignLine->setId(Uuid::v7());
        $foreignLine->setFuelType('diesel');
        $foreignLine->setQuantityMilliLiters(23000);
        $foreignLine->setUnitPriceDeciCentsPerLiter(1826);
        $foreignLine->setVatRatePercent(20);
        $foreignReceipt->addLine($foreignLine);
        $this->em->persist($foreignReceipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $invalidCsrfResponse = $this->request('POST', '/ui/stations/'.$visibleStation->getId()->toRfc4122().'/toggle-favorite', [
            '_token' => 'invalid-token',
            '_redirect' => '/ui/stations',
        ]);
        self::assertSame(Response::HTTP_FOUND, $invalidCsrfResponse->getStatusCode());
        self::assertSame('/ui/stations', $invalidCsrfResponse->headers->get('Location'));
        self::assertSame(0, $this->favoriteCount($owner->getId()->toRfc4122(), $visibleStation->getId()->toRfc4122()));

        $afterInvalidCsrf = $this->request('GET', '/ui/stations');
        self::assertSame(Response::HTTP_OK, $afterInvalidCsrf->getStatusCode());
        self::assertStringContainsString('Jeton CSRF invalide.', (string) $afterInvalidCsrf->getContent());

        $pageResponse = $this->request('GET', '/ui/stations');
        self::assertSame(Response::HTTP_OK, $pageResponse->getStatusCode());
        $csrfToken = $this->extractFavoriteToggleToken((string) $pageResponse->getContent(), $visibleStation->getId()->toRfc4122());

        $inaccessibleResponse = $this->request('POST', '/ui/stations/'.$foreignStation->getId()->toRfc4122().'/toggle-favorite', [
            '_token' => $csrfToken,
            '_redirect' => '/ui/stations',
        ]);
        self::assertSame(Response::HTTP_NOT_FOUND, $inaccessibleResponse->getStatusCode());
        self::assertSame(0, $this->favoriteCount($owner->getId()->toRfc4122(), $foreignStation->getId()->toRfc4122()));
    }

    /**
     * @param array<string, string|int|float|bool|array<int, array<string, string>>|null> $parameters
     * @param array<string, string>                                                       $server
     */
    private function request(string $method, string $uri, array $parameters = [], array $server = []): Response
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

    private function extractFavoriteToggleToken(string $content, string $stationId): string
    {
        $pattern = sprintf('/action="\\/ui\\/stations\\/%s\\/toggle-favorite".*?name="_token" value="([^"]+)"/s', preg_quote($stationId, '/'));
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private function favoriteCount(string $ownerId, string $stationId): int
    {
        $count = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM favorite_stations WHERE owner_id = :owner_id AND station_id = :station_id',
            [
                'owner_id' => $ownerId,
                'station_id' => $stationId,
            ],
        );

        self::assertIsNumeric($count);

        return (int) $count;
    }
}
