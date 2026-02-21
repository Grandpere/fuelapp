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

namespace App\Tests\Functional\Vehicle;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Application\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class VehicleWebUiTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;
    private VehicleRepository $vehicleRepository;

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

        $vehicleRepository = $container->get(VehicleRepository::class);
        if (!$vehicleRepository instanceof VehicleRepository) {
            throw new RuntimeException('VehicleRepository service is invalid.');
        }
        $this->vehicleRepository = $vehicleRepository;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testUserCanCreateEditAndDeleteVehicleFromUi(): void
    {
        $email = 'vehicle.ui.owner@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);
        $this->em->flush();

        $ownerId = $owner->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($email, $password);

        $newPage = $this->request('GET', '/ui/vehicles/new', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $newPage->getStatusCode());
        $newCsrf = $this->extractFormCsrf((string) $newPage->getContent());

        $createResponse = $this->request(
            'POST',
            '/ui/vehicles/new',
            [
                'name' => 'Family Car',
                'plateNumber' => 'AA-111-BB',
                '_token' => $newCsrf,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $createResponse->getStatusCode());

        $vehicle = $this->vehicleRepository->findByOwnerAndPlateNumber($ownerId, 'AA-111-BB');
        self::assertNotNull($vehicle);
        $vehicleId = $vehicle->id()->toString();

        $editPage = $this->request('GET', '/ui/vehicles/'.$vehicleId.'/edit', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $editPage->getStatusCode());
        $editCsrf = $this->extractFormCsrf((string) $editPage->getContent());

        $editResponse = $this->request(
            'POST',
            '/ui/vehicles/'.$vehicleId.'/edit',
            [
                'name' => 'Family Car Updated',
                'plateNumber' => 'AA-222-CC',
                '_token' => $editCsrf,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $editResponse->getStatusCode());

        $updated = $this->vehicleRepository->findByOwnerAndPlateNumber($ownerId, 'AA-222-CC');
        self::assertNotNull($updated);

        $listPage = $this->request('GET', '/ui/vehicles', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listPage->getStatusCode());
        self::assertStringContainsString('Family Car Updated', (string) $listPage->getContent());
        $deleteToken = $this->extractDeleteCsrfForVehicle((string) $listPage->getContent(), $vehicleId);

        $deleteResponse = $this->request(
            'POST',
            '/ui/vehicles/'.$vehicleId.'/delete',
            [
                '_token' => $deleteToken,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $deleteResponse->getStatusCode());

        self::assertNull($this->vehicleRepository->get($vehicleId));
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

    private function extractFormCsrf(string $content): string
    {
        self::assertMatchesRegularExpression('/name="_token" value="([^"]+)"/', $content);
        preg_match('/name="_token" value="([^"]+)"/', $content, $matches);

        $token = $matches[1] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private function extractDeleteCsrfForVehicle(string $content, string $vehicleId): string
    {
        $pattern = '#/ui/vehicles/'.preg_quote($vehicleId, '#').'/delete.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
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
