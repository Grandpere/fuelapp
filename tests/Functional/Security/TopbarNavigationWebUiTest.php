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

final class TopbarNavigationWebUiTest extends KernelTestCase
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

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE admin_audit_logs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, maintenance_planned_costs, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testRoleUserSeesContactLinkWithoutBackofficeShortcut(): void
    {
        $email = 'topbar.user@example.com';
        $password = 'test1234';
        $this->createUser($email, $password, ['ROLE_USER']);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $receiptsResponse = $this->request('GET', '/ui/receipts', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $receiptsResponse->getStatusCode());
        $receiptsContent = (string) $receiptsResponse->getContent();
        self::assertStringContainsString('>Contact<', $receiptsContent);
        self::assertStringNotContainsString('>Back-office<', $receiptsContent);

        $contactResponse = $this->request('GET', '/ui/contact', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $contactResponse->getStatusCode());
        self::assertStringContainsString('Support email', (string) $contactResponse->getContent());
    }

    public function testAdminSeesBackofficeShortcutInTopbar(): void
    {
        $email = 'topbar.admin@example.com';
        $password = 'test1234';
        $this->createUser($email, $password, ['ROLE_ADMIN']);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $receiptsResponse = $this->request('GET', '/ui/receipts', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $receiptsResponse->getStatusCode());
        $content = (string) $receiptsResponse->getContent();
        self::assertStringContainsString('>Contact<', $content);
        self::assertStringContainsString('>Back-office<', $content);
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
