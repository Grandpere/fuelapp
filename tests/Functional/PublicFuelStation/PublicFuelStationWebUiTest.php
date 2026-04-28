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

namespace App\Tests\Functional\PublicFuelStation;

use App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity\PublicFuelStationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class PublicFuelStationWebUiTest extends WebTestCase
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

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE public_fuel_station_sync_runs, public_fuel_stations, users CASCADE');
    }

    public function testUserCanBrowseCachedPublicFuelStationsWithFuelFilter(): void
    {
        $email = 'public.station.ui@example.com';
        $password = 'test1234';
        $this->createUser($email, $password, ['ROLE_USER']);
        $this->persistPublicStation('1000001', '596 AVENUE DE TREVOUX', '01000', 'SAINT-DENIS-LÈS-BOURG', true, true);
        $this->persistPublicStation('1000003', '12 </script><script>alert(1)</script> AVENUE', '01000', 'BOURG-ESCAPE', true, true);
        $this->persistPublicStation('2000002', '8 ROUTE SANS GAZOLE', '69000', 'LYON', false, false);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $response = $this->request('GET', '/ui/public-fuel-stations?fuel=gazole&q=01000');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();

        self::assertStringContainsString('Public Fuel Stations', $content);
        self::assertStringContainsString('Station list', $content);
        self::assertStringContainsString('SAINT-DENIS-LÈS-BOURG', $content);
        self::assertStringContainsString('596 AVENUE DE TREVOUX', $content);
        self::assertStringContainsString('1.789 EUR/L', $content);
        self::assertStringContainsString('public-fuel-station-map-data', $content);
        self::assertStringContainsString('"latitude":49.569', $content);
        self::assertStringContainsString('\u003C\/script\u003E\u003Cscript\u003Ealert(1)\u003C\/script\u003E', $content);
        self::assertStringNotContainsString('8 ROUTE SANS GAZOLE', $content);
    }

    private function persistPublicStation(string $sourceId, string $address, string $postalCode, string $city, bool $available, bool $withPrice): void
    {
        $station = new PublicFuelStationEntity();
        $station->setSourceId($sourceId);
        $station->setLatitudeMicroDegrees(49569000);
        $station->setLongitudeMicroDegrees(3646000);
        $station->setAddress($address);
        $station->setPostalCode($postalCode);
        $station->setCity($city);
        $station->setPopulationKind('R');
        $station->setDepartment('Ain');
        $station->setDepartmentCode('01');
        $station->setRegion('Auvergne-Rhône-Alpes');
        $station->setRegionCode('84');
        $station->setAutomate24(true);
        $station->setServices(['Boutique alimentaire']);
        $station->setFuels([
            'gazole' => [
                'available' => $available,
                'priceMilliEurosPerLiter' => $withPrice ? 1789 : null,
                'priceUpdatedAt' => '2026-04-28T09:15:00+02:00',
                'ruptureType' => $available ? null : 'temporaire',
                'ruptureStartedAt' => $available ? null : '2026-04-28T10:00:00+02:00',
            ],
        ]);
        $station->setSourceUpdatedAt(new DateTimeImmutable('2026-04-28 09:15:00'));
        $station->setImportedAt(new DateTimeImmutable('2026-04-28 09:20:00'));
        $this->em->persist($station);
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

    private function loginWithUiForm(string $email, string $password): void
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
