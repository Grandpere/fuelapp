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

namespace App\Tests\Functional\Maintenance;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class MaintenancePlannedCostApiTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private VehicleRepository $vehicleRepository;
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

        $vehicleRepository = $container->get(VehicleRepository::class);
        if (!$vehicleRepository instanceof VehicleRepository) {
            throw new RuntimeException('VehicleRepository service is invalid.');
        }
        $this->vehicleRepository = $vehicleRepository;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testUserCanCrudPlannedCostsAndQueryVariance(): void
    {
        $owner = $this->createUser('maintenance.cost.owner@example.com', 'test1234', ['ROLE_USER']);
        $this->em->flush();

        $vehicle = Vehicle::create($owner->getId()->toRfc4122(), 'C3', 'aa-555-bb');
        $this->vehicleRepository->save($vehicle);

        $token = $this->apiLogin('maintenance.cost.owner@example.com', 'test1234');

        $createPlanResponse = $this->request(
            'POST',
            '/api/maintenance/plans',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([
                'vehicleId' => $vehicle->id()->toString(),
                'label' => 'Major service',
                'eventType' => 'service',
                'plannedFor' => '2026-06-15T09:00:00+00:00',
                'plannedCostCents' => 50000,
                'currencyCode' => 'EUR',
                'notes' => 'target budget',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $createPlanResponse->getStatusCode());
        /** @var array{id: string} $plan */
        $plan = json_decode((string) $createPlanResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $planId = $plan['id'];

        $listResponse = $this->request(
            'GET',
            '/api/maintenance/plans',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());

        $createEventResponse = $this->request(
            'POST',
            '/api/maintenance/events',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([
                'vehicleId' => $vehicle->id()->toString(),
                'eventType' => 'service',
                'occurredAt' => '2026-06-20T10:00:00+00:00',
                'description' => 'performed',
                'odometerKilometers' => 120000,
                'totalCostCents' => 62000,
                'currencyCode' => 'EUR',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $createEventResponse->getStatusCode());

        $varianceResponse = $this->request(
            'GET',
            '/api/maintenance/cost-variance?vehicleId='.$vehicle->id()->toString().'&from=2026-01-01&to=2026-12-31',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $varianceResponse->getStatusCode());

        $decodedVariance = json_decode((string) $varianceResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $items = $this->extractCollectionItems($decodedVariance);
        self::assertCount(1, $items);
        self::assertSame(50000, $items[0]['plannedCostCents'] ?? null);
        self::assertSame(62000, $items[0]['actualCostCents'] ?? null);
        self::assertSame(12000, $items[0]['varianceCents'] ?? null);

        $patchPlanResponse = $this->request(
            'PATCH',
            '/api/maintenance/plans/'.$planId,
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ],
            json_encode([
                'vehicleId' => $vehicle->id()->toString(),
                'label' => 'Major service updated',
                'eventType' => 'service',
                'plannedFor' => '2026-06-15T09:00:00+00:00',
                'plannedCostCents' => 55000,
                'currencyCode' => 'EUR',
                'notes' => 'target budget adjusted',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $patchPlanResponse->getStatusCode());

        $deletePlanResponse = $this->request(
            'DELETE',
            '/api/maintenance/plans/'.$planId,
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_NO_CONTENT, $deletePlanResponse->getStatusCode());
    }

    /** @param array<string, string> $server */
    private function request(string $method, string $uri, array $server = [], ?string $content = null): Response
    {
        $request = Request::create($uri, $method, server: $server, content: $content);
        $response = $this->httpKernel->handle($request);
        $this->terminableKernel?->terminate($request, $response);

        return $response;
    }

    /** @return list<array<string, mixed>> */
    private function extractCollectionItems(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        $items = $decoded;
        if (array_key_exists('member', $decoded) && is_array($decoded['member'])) {
            $items = $decoded['member'];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $normalized = [];
                foreach ($item as $key => $value) {
                    if (is_string($key)) {
                        $normalized[$key] = $value;
                    }
                }
                $result[] = $normalized;
            }
        }

        return $result;
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
