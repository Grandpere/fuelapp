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

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
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
        $this->em->persist($station);

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
        self::assertStringContainsString('Receipts tracked', $content);
        self::assertStringContainsString('Latest receipts', $content);
        self::assertStringContainsString('/ui/receipts?station_id='.$stationId, $content);
        self::assertStringContainsString('/ui/analytics?station_id='.$stationId, $content);
        self::assertStringContainsString('/ui/receipts/new?station_id='.$stationId, $content);
        self::assertStringContainsString('/ui/receipts/'.$receipt->getId()->toRfc4122(), $content);
        self::assertStringContainsString('145200 km', $content);
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
}
