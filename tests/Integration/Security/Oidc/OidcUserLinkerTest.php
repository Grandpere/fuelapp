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

namespace App\Tests\Integration\Security\Oidc;

use App\Security\Oidc\OidcUserLinker;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserIdentityEntity;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class OidcUserLinkerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private OidcUserLinker $linker;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service is invalid.');
        }
        $this->em = $em;

        $linker = $container->get(OidcUserLinker::class);
        if (!$linker instanceof OidcUserLinker) {
            throw new RuntimeException('OidcUserLinker service is invalid.');
        }
        $this->linker = $linker;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('PasswordHasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE user_identities, users, receipt_lines, receipts, stations CASCADE');
    }

    public function testResolveUserCreatesLocalUserAndIdentityWhenMissing(): void
    {
        $user = $this->linker->resolveUser('auth0', 'sub-1', 'john@example.com');

        self::assertSame('john@example.com', $user->getEmail());

        /** @var UserIdentityEntity|null $identity */
        $identity = $this->em->getRepository(UserIdentityEntity::class)->findOneBy([
            'provider' => 'auth0',
            'subject' => 'sub-1',
        ]);
        self::assertNotNull($identity);
        self::assertSame($user->getId()->toRfc4122(), $identity->getUser()->getId()->toRfc4122());
    }

    public function testResolveUserLinksToExistingUserByEmail(): void
    {
        $existing = new UserEntity();
        $existing->setId(Uuid::v7());
        $existing->setEmail('existing@example.com');
        $existing->setRoles(['ROLE_USER']);
        $existing->setPassword($this->passwordHasher->hashPassword($existing, 'test1234'));
        $this->em->persist($existing);
        $this->em->flush();

        $resolved = $this->linker->resolveUser('auth0', 'sub-2', 'existing@example.com');

        self::assertSame($existing->getId()->toRfc4122(), $resolved->getId()->toRfc4122());
    }

    public function testResolveUserReturnsExistingLinkedIdentity(): void
    {
        $existing = new UserEntity();
        $existing->setId(Uuid::v7());
        $existing->setEmail('identity@example.com');
        $existing->setRoles(['ROLE_USER']);
        $existing->setPassword($this->passwordHasher->hashPassword($existing, 'test1234'));
        $this->em->persist($existing);

        $identity = new UserIdentityEntity();
        $identity->setId(Uuid::v7());
        $identity->setUser($existing);
        $identity->setProvider('auth0');
        $identity->setSubject('sub-3');
        $identity->setEmail('identity@example.com');
        $this->em->persist($identity);
        $this->em->flush();

        $resolved = $this->linker->resolveUser('auth0', 'sub-3', 'other@example.com');

        self::assertSame($existing->getId()->toRfc4122(), $resolved->getId()->toRfc4122());
    }
}
