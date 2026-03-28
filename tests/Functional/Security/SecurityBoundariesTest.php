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

namespace App\Tests\Functional\Security;

use App\Admin\Infrastructure\Persistence\Doctrine\Entity\AdminAuditLogEntity;
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
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class SecurityBoundariesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $kernel = static::getContainer()->get(HttpKernelInterface::class);
        if (!$kernel instanceof HttpKernelInterface) {
            throw new RuntimeException('HttpKernel service is invalid.');
        }
        $this->httpKernel = $kernel;
        $this->terminableKernel = $kernel instanceof TerminableInterface ? $kernel : null;

        $em = static::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service is invalid.');
        }
        $this->em = $em;

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->resetDatabase();
    }

    public function testAnonymousUserIsRedirectedToLoginOnUiRoute(): void
    {
        $response = $this->request('GET', '/ui/receipts');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $response->headers->get('Location'));
    }

    public function testAnonymousUserGets401OnApiRoute(): void
    {
        $response = $this->request('GET', '/api/receipts');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testPublicUiRouteExposesSecurityHeadersBaseline(): void
    {
        $response = $this->request('GET', '/ui/login');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertSame('deny', mb_strtolower((string) $response->headers->get('X-Frame-Options')));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame(
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
            $response->headers->get('Permissions-Policy'),
        );

        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertStringContainsString("object-src 'none'", $csp);
        self::assertStringContainsString("form-action 'self'", $csp);
        self::assertStringContainsString("style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com", $csp);
        self::assertStringContainsString("font-src 'self' data:", $csp);
        self::assertStringContainsString("script-src 'self' 'unsafe-inline' data: https://unpkg.com", $csp);
        self::assertStringContainsString("connect-src 'self' https:", $csp);
        self::assertStringContainsString('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', (string) $response->getContent());
        self::assertStringContainsString('data-theme-toggle', (string) $response->getContent());
        self::assertStringContainsString('fuelapp-theme', (string) $response->getContent());
        self::assertStringContainsString('fuelapp:theme-changed', (string) $response->getContent());
        self::assertStringContainsString('data-theme-ready', (string) $response->getContent());
    }

    public function testApiDocsAlsoExposeSecurityHeadersBaseline(): void
    {
        $response = $this->request('GET', '/api/docs.jsonopenapi');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertSame('deny', mb_strtolower((string) $response->headers->get('X-Frame-Options')));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    public function testApiLoginFailureWithOverlongEmailDoesNotReturnServerError(): void
    {
        $overlongEmail = str_repeat('ab', 70).'@example.com';
        $response = $this->request(
            'POST',
            '/api/login',
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $overlongEmail, 'password' => 'wrong-password'], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('Invalid credentials.', (string) $response->getContent());

        $entry = $this->em->getRepository(AdminAuditLogEntity::class)->findOneBy(
            ['action' => 'security.login.failure'],
            ['createdAt' => 'DESC'],
        );
        self::assertInstanceOf(AdminAuditLogEntity::class, $entry);
        self::assertSame(120, mb_strlen($entry->getTargetId()));
        self::assertSame(mb_substr(mb_strtolower(trim($overlongEmail)), 0, 120), $entry->getTargetId());
    }

    public function testApiLoginFailureWithOverlongCorrelationHeaderDoesNotReturnServerError(): void
    {
        $overlongCorrelationId = str_repeat('req-', 30);

        $response = $this->request(
            'POST',
            '/api/login',
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CORRELATION_ID' => $overlongCorrelationId,
            ],
            json_encode(['email' => 'missing@example.com', 'password' => 'wrong-password'], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('Invalid credentials.', (string) $response->getContent());
        self::assertSame(mb_substr($overlongCorrelationId, 0, 80), (string) $response->headers->get('X-Correlation-Id'));

        $entry = $this->em->getRepository(AdminAuditLogEntity::class)->findOneBy(
            ['action' => 'security.login.failure'],
            ['createdAt' => 'DESC'],
        );
        self::assertInstanceOf(AdminAuditLogEntity::class, $entry);
        self::assertSame(80, mb_strlen($entry->getCorrelationId()));
        self::assertSame(mb_substr($overlongCorrelationId, 0, 80), $entry->getCorrelationId());
    }

    public function testApiLoginRateLimitReturns429AfterTooManyAttempts(): void
    {
        $email = sprintf('rate-limit-%s@example.com', uniqid('', true));

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $response = $this->request(
                'POST',
                '/api/login',
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['email' => $email, 'password' => 'wrong-password'], JSON_THROW_ON_ERROR),
            );

            self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        }

        $limitedResponse = $this->request(
            'POST',
            '/api/login',
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => 'wrong-password'], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $limitedResponse->getStatusCode());
        self::assertStringContainsString('Too many login attempts', (string) $limitedResponse->getContent());
        self::assertNotNull($limitedResponse->headers->get('Retry-After'));
    }

    public function testApiLoginOversizedPayloadReturns413(): void
    {
        $oversizedPayload = json_encode([
            'email' => str_repeat('a', 5000).'@example.com',
            'password' => 'x',
        ], JSON_THROW_ON_ERROR);

        $response = $this->request(
            'POST',
            '/api/login',
            ['CONTENT_TYPE' => 'application/json'],
            $oversizedPayload,
        );

        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
        self::assertStringContainsString('Request payload too large.', (string) $response->getContent());
    }

    public function testAnonymousUserGets401OnAdminApiPrefix(): void
    {
        $response = $this->request('GET', '/api/admin/ping');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testRoleUserCannotAccessAdminApiPrefix(): void
    {
        $email = 'user.admin.blocked@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $response = $this->request(
            'GET',
            '/api/admin/ping',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testRoleAdminCanPassAdminApiGate(): void
    {
        $email = 'admin.allowed@example.com';
        $password = 'test1234';
        $this->createUser($email, $password, ['ROLE_ADMIN']);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $response = $this->request(
            'GET',
            '/api/admin/ping',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDisabledUserCannotReusePreviouslyIssuedApiToken(): void
    {
        $email = 'disabled.token@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $user->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $response = $this->request(
            'GET',
            '/api/receipts',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('Account disabled.', (string) $response->getContent());
    }

    public function testDisabledUserApiLoginDoesNotExposeAccountStatus(): void
    {
        $email = 'disabled.api.login@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);
        $user->setIsActive(false);
        $this->em->flush();

        $response = $this->request(
            'POST',
            '/api/login',
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('Invalid credentials.', (string) $response->getContent());
        self::assertStringNotContainsString('Account disabled.', (string) $response->getContent());

        $entry = $this->em->getRepository(AdminAuditLogEntity::class)->findOneBy(
            ['action' => 'security.login.failure'],
            ['createdAt' => 'DESC'],
        );
        self::assertInstanceOf(AdminAuditLogEntity::class, $entry);
        self::assertSame('Account disabled.', $entry->getMetadata()['reason'] ?? null);
    }

    public function testAnonymousUserIsRedirectedOnAdminUiPrefix(): void
    {
        $response = $this->request('GET', '/ui/admin');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $response->headers->get('Location'));
    }

    public function testAnonymousUserIsRedirectedOnAdminUiEntityPages(): void
    {
        $stationsResponse = $this->request('GET', '/ui/admin/stations');
        $vehiclesResponse = $this->request('GET', '/ui/admin/vehicles');
        $importsResponse = $this->request('GET', '/ui/admin/imports');
        $identitiesResponse = $this->request('GET', '/ui/admin/identities');
        $securityActivitiesResponse = $this->request('GET', '/ui/admin/security-activities');
        $auditResponse = $this->request('GET', '/ui/admin/audit-logs');

        self::assertSame(Response::HTTP_FOUND, $stationsResponse->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $stationsResponse->headers->get('Location'));
        self::assertSame(Response::HTTP_FOUND, $vehiclesResponse->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $vehiclesResponse->headers->get('Location'));
        self::assertSame(Response::HTTP_FOUND, $importsResponse->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $importsResponse->headers->get('Location'));
        self::assertSame(Response::HTTP_FOUND, $identitiesResponse->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $identitiesResponse->headers->get('Location'));
        self::assertSame(Response::HTTP_FOUND, $securityActivitiesResponse->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $securityActivitiesResponse->headers->get('Location'));
        self::assertSame(Response::HTTP_FOUND, $auditResponse->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $auditResponse->headers->get('Location'));
    }

    public function testAnonymousUserCanAccessApiDocs(): void
    {
        $htmlResponse = $this->request('GET', '/api/docs');
        self::assertSame(Response::HTTP_OK, $htmlResponse->getStatusCode());
        self::assertNotNull($htmlResponse->headers->get('X-Correlation-Id'));
        self::assertTrue(Uuid::isValid((string) $htmlResponse->headers->get('X-Correlation-Id')));

        $openApiResponse = $this->request('GET', '/api/docs.jsonopenapi');
        self::assertSame(Response::HTTP_OK, $openApiResponse->getStatusCode());
        self::assertNotNull($openApiResponse->headers->get('X-Correlation-Id'));
        self::assertTrue(Uuid::isValid((string) $openApiResponse->headers->get('X-Correlation-Id')));

        /** @var array{paths?: array<string, mixed>} $openApi */
        $openApi = json_decode((string) $openApiResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $paths = $openApi['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertArrayHasKey('/api/imports', $paths);

        $importsPath = $paths['/api/imports'] ?? null;
        self::assertIsArray($importsPath);
        self::assertArrayHasKey('post', $importsPath);
    }

    public function testOidcLoginRoutesArePublic(): void
    {
        $response = $this->request('GET', '/ui/login/oidc/unknown');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $response->headers->get('Location'));
    }

    public function testUserCannotAccessAnotherUsersReceiptFromApi(): void
    {
        $owner = $this->createUser('owner2@example.com', 'test1234');
        $otherUserEmail = 'other2@example.com';
        $otherUserPassword = 'test1234';
        $this->createUser($otherUserEmail, $otherUserPassword);

        $stationId = $this->createStation('Station B');
        $receiptId = $this->createReceipt($owner, $stationId);
        $this->em->flush();

        $token = $this->apiLogin($otherUserEmail, $otherUserPassword);

        $response = $this->request(
            'GET',
            sprintf('/api/receipts/%s', $receiptId->toRfc4122()),
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUserCannotAccessAnotherUsersStationFromApi(): void
    {
        $owner = $this->createUser('owner3@example.com', 'test1234');
        $otherUserEmail = 'other3@example.com';
        $otherUserPassword = 'test1234';
        $this->createUser($otherUserEmail, $otherUserPassword);

        $stationId = $this->createStation('Station C');
        $this->createReceipt($owner, $stationId);
        $this->em->flush();

        $token = $this->apiLogin($otherUserEmail, $otherUserPassword);
        $response = $this->request(
            'GET',
            sprintf('/api/stations/%s', $stationId->toRfc4122()),
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /** @param array<string, string> $server */
    private function request(string $method, string $uri, array $server = [], ?string $content = null): Response
    {
        $request = Request::create($uri, $method, server: $server, content: $content);
        $response = $this->httpKernel->handle($request);
        $this->terminableKernel?->terminate($request, $response);

        return $response;
    }

    private function apiLogin(string $email, string $password): string
    {
        $response = $this->request(
            'POST',
            '/api/login',
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{token: string} $data */
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $data['token'];
    }

    private function resetDatabase(): void
    {
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE admin_audit_logs, receipt_lines, receipts, stations, users CASCADE');
    }

    /** @param list<string> $roles */
    private function createUser(string $email, string $plainPassword, array $roles = ['ROLE_USER']): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->em->persist($user);

        return $user;
    }

    private function createStation(string $name): UuidV7
    {
        $station = new StationEntity();
        $id = Uuid::v7();

        $station->setId($id);
        $station->setName($name);
        $station->setStreetName('1 Main St');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $station->setLatitudeMicroDegrees(null);
        $station->setLongitudeMicroDegrees(null);
        $this->em->persist($station);

        return $id;
    }

    private function createReceipt(UserEntity $owner, UuidV7 $stationId): UuidV7
    {
        $receipt = new ReceiptEntity();
        $receiptId = Uuid::v7();

        $station = $this->em->getReference(StationEntity::class, $stationId->toRfc4122());

        $receipt->setId($receiptId);
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-02-20 10:00:00'));
        $receipt->setTotalCents(1796);
        $receipt->setVatAmountCents(299);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(9560);
        $line->setUnitPriceDeciCentsPerLiter(1879);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);

        return $receiptId;
    }
}
