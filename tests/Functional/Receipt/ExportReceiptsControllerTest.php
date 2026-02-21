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

use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class ExportReceiptsControllerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $kernel = $container->get(HttpKernelInterface::class);
        if (!$kernel instanceof HttpKernelInterface) {
            throw new RuntimeException('HttpKernel service is invalid.');
        }
        $this->httpKernel = $kernel;
        $this->terminableKernel = $kernel instanceof TerminableInterface ? $kernel : null;

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

        $this->em->getConnection()->executeStatement(
            'TRUNCATE TABLE analytics_projection_states, analytics_daily_fuel_kpis, maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE',
        );
    }

    public function testCsvExportStreamsRowsWithMetadataAndAppliedFilters(): void
    {
        $owner = $this->createUser('receipt.export.owner@example.com', 'test1234', ['ROLE_USER']);
        $stationA = $this->createStation('Station A', '1 Main St', '75001', 'Paris');
        $stationB = $this->createStation('Station B', '2 Oak Ave', '69001', 'Lyon');
        $this->em->flush();

        $this->createReceipt($owner, $stationA, new DateTimeImmutable('2026-02-10 10:00:00'), 'diesel', 10000, 18000, 20);
        $this->createReceipt($owner, $stationB, new DateTimeImmutable('2026-02-12 10:00:00'), 'unleaded95', 15000, 17000, 20);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm('receipt.export.owner@example.com', 'test1234');

        $response = $this->request(
            'GET',
            '/ui/receipts/export?issued_from=2026-02-01&issued_to=2026-02-28&station_id='.$stationA->getId()->toRfc4122().'&fuel_type=diesel&sort_by=date&sort_direction=desc',
            [],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $this->responseContent($response);
        self::assertStringContainsString('generated_at,', $content);
        self::assertStringContainsString('filter_station_id,'.$stationA->getId()->toRfc4122(), $content);
        self::assertStringContainsString('filter_fuel_type,diesel', $content);
        self::assertStringContainsString('receipt_id,issued_at,station_name', $content);
        self::assertStringContainsString('Station A', $content);
        self::assertStringNotContainsString('Station B', $content);
    }

    public function testXlsxExportReturnsSpreadsheetDownload(): void
    {
        $owner = $this->createUser('receipt.export.xlsx@example.com', 'test1234', ['ROLE_USER']);
        $station = $this->createStation('Station XLSX', '3 Elm St', '13001', 'Marseille');
        $this->em->flush();

        $this->createReceipt($owner, $station, new DateTimeImmutable('2026-02-10 10:00:00'), 'diesel', 10000, 18000, 20);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm('receipt.export.xlsx@example.com', 'test1234');

        $response = $this->request(
            'GET',
            '/ui/receipts/export?format=xlsx&issued_from=2026-02-01&issued_to=2026-02-28',
            [],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
        );
        self::assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));

        $content = $this->responseContent($response);
        self::assertTrue(str_starts_with($content, 'PK'));
    }

    /**
     * @param array<string, string|int|float|bool|null> $parameters
     * @param array<string, string>                     $server
     * @param array<string, string>                     $cookies
     */
    private function request(string $method, string $uri, array $parameters = [], array $server = [], array $cookies = []): Response
    {
        $request = Request::create($uri, $method, $parameters, $cookies, server: $server);
        $response = $this->httpKernel->handle($request);
        $this->terminableKernel?->terminate($request, $response);

        return $response;
    }

    /** @return array<string, string> */
    private function loginWithUiForm(string $email, string $password): array
    {
        $loginPageResponse = $this->request('GET', '/ui/login');
        self::assertSame(Response::HTTP_OK, $loginPageResponse->getStatusCode());

        $sessionCookie = $this->extractSessionCookie($loginPageResponse);
        self::assertNotEmpty($sessionCookie);

        self::assertMatchesRegularExpression('/name="_csrf_token" value="([^"]+)"/', (string) $loginPageResponse->getContent());
        preg_match('/name="_csrf_token" value="([^"]+)"/', (string) $loginPageResponse->getContent(), $matches);
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
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());

        return $this->extractSessionCookie($loginResponse) ?: $sessionCookie;
    }

    /** @return array<string, string> */
    private function extractSessionCookie(Response $response): array
    {
        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            if (str_starts_with($cookie->getName(), 'MOCKSESSID') || str_starts_with($cookie->getName(), 'PHPSESSID')) {
                return [$cookie->getName() => (string) $cookie->getValue()];
            }
        }

        return [];
    }

    private function responseContent(Response $response): string
    {
        if ($response instanceof StreamedResponse) {
            ob_start();
            $response->sendContent();

            return (string) ob_get_clean();
        }

        return (string) $response->getContent();
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

    private function createStation(string $name, string $street, string $postalCode, string $city): StationEntity
    {
        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName($name);
        $station->setStreetName($street);
        $station->setPostalCode($postalCode);
        $station->setCity($city);
        $this->em->persist($station);

        return $station;
    }

    private function createReceipt(
        UserEntity $owner,
        ?StationEntity $station,
        DateTimeImmutable $issuedAt,
        string $fuelType,
        int $quantityMilliLiters,
        int $unitPriceDeciCentsPerLiter,
        int $vatRatePercent,
    ): void {
        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setVehicle(null);
        $receipt->setIssuedAt($issuedAt);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType($fuelType);
        $line->setQuantityMilliLiters($quantityMilliLiters);
        $line->setUnitPriceDeciCentsPerLiter($unitPriceDeciCentsPerLiter);
        $line->setVatRatePercent($vatRatePercent);
        $receipt->addLine($line);

        $lineTotal = (int) round(($unitPriceDeciCentsPerLiter * $quantityMilliLiters) / 10000, 0, PHP_ROUND_HALF_UP);
        $vatAmount = (int) round($lineTotal * $vatRatePercent / (100 + $vatRatePercent), 0, PHP_ROUND_HALF_UP);
        $receipt->setTotalCents($lineTotal);
        $receipt->setVatAmountCents($vatAmount);

        $this->em->persist($receipt);
    }
}
