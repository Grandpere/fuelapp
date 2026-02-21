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

namespace App\Tests\Integration\Import\Infrastructure;

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\ImportJob;
use App\Import\Infrastructure\Persistence\Doctrine\Repository\DoctrineImportJobRepository;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class DoctrineImportJobRepositoryOwnershipTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ImportJobRepository $importJobRepository;
    private TokenStorageInterface $tokenStorage;
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

        $tokenStorage = $container->get(TokenStorageInterface::class);
        if (!$tokenStorage instanceof TokenStorageInterface) {
            throw new RuntimeException('TokenStorage service is invalid.');
        }
        $this->tokenStorage = $tokenStorage;
        $this->importJobRepository = new DoctrineImportJobRepository($this->em, $this->tokenStorage);

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('PasswordHasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testRepositoryIsScopedToCurrentAuthenticatedUser(): void
    {
        $owner = $this->createUser('owner.import@example.com');
        $other = $this->createUser('other.import@example.com');
        $this->em->flush();

        $this->authenticate($owner);
        $job = ImportJob::createQueued(
            $owner->getId()->toRfc4122(),
            'local',
            '2026/02/21/receipt.pdf',
            'receipt.pdf',
            'application/pdf',
            1200,
            str_repeat('a', 64),
        );
        $this->importJobRepository->save($job);

        self::assertNotNull($this->importJobRepository->get($job->id()->toString()));
        self::assertNotNull($this->importJobRepository->getForSystem($job->id()->toString()));

        $this->authenticate($other);
        self::assertNull($this->importJobRepository->get($job->id()->toString()));
        self::assertNotNull($this->importJobRepository->getForSystem($job->id()->toString()));
    }

    private function createUser(string $email): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'test1234'));
        $this->em->persist($user);

        return $user;
    }

    private function authenticate(UserEntity $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
