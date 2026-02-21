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

namespace App\Tests\Functional\Admin;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class AdminAuditTrailApiTest extends KernelTestCase
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

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE admin_audit_logs, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testAdminMutationCreatesAuditEntryWithCorrelationId(): void
    {
        $admin = $this->createUser('audit.admin@example.com', 'test1234', ['ROLE_ADMIN']);
        $this->em->flush();

        $token = $this->apiLogin('audit.admin@example.com', 'test1234');

        $correlationId = 'corr-audit-20260221';
        $createResponse = $this->request(
            'POST',
            '/api/admin/stations',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_X_CORRELATION_ID' => $correlationId,
            ],
            json_encode([
                'name' => 'Audit Station',
                'streetName' => '10 Rue Audit',
                'postalCode' => '75001',
                'city' => 'Paris',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $createResponse->getStatusCode());

        $logsResponse = $this->request(
            'GET',
            '/api/admin/audit-logs?action=admin.station.created',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $logsResponse->getStatusCode());

        $decoded = json_decode((string) $logsResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $items = $this->extractCollectionItems($decoded);
        self::assertNotEmpty($items);

        $entry = $items[0];
        self::assertSame($admin->getId()->toRfc4122(), $entry['actorId'] ?? null);
        self::assertSame('audit.admin@example.com', $entry['actorEmail'] ?? null);
        self::assertSame('admin.station.created', $entry['action'] ?? null);
        self::assertSame('station', $entry['targetType'] ?? null);
        self::assertSame($correlationId, $entry['correlationId'] ?? null);
        self::assertIsArray($entry['diffSummary'] ?? null);
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
}
