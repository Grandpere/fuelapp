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

namespace App\Tests\Integration\Receipt\Infrastructure;

use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\Domain\Receipt;
use App\Receipt\Domain\ReceiptLine;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class DoctrineReceiptRepositoryOwnershipTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ReceiptRepository $receiptRepository;
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

        $receiptRepository = $container->get(ReceiptRepository::class);
        if (!$receiptRepository instanceof ReceiptRepository) {
            throw new RuntimeException('ReceiptRepository service is invalid.');
        }
        $this->receiptRepository = $receiptRepository;

        $tokenStorage = $container->get(TokenStorageInterface::class);
        if (!$tokenStorage instanceof TokenStorageInterface) {
            throw new RuntimeException('TokenStorage service is invalid.');
        }
        $this->tokenStorage = $tokenStorage;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE receipt_lines, receipts, users, stations CASCADE');
    }

    public function testRepositoryIsScopedToCurrentAuthenticatedUser(): void
    {
        $owner = $this->createUser('owner.repo@example.com');
        $other = $this->createUser('other.repo@example.com');
        $this->em->flush();

        $this->authenticate($owner);
        $receipt = Receipt::create(
            new DateTimeImmutable('2026-02-20 09:00:00'),
            [ReceiptLine::create(FuelType::DIESEL, 10_000, 1800, 20)],
            null,
        );
        $this->receiptRepository->save($receipt);

        self::assertSame(1, $this->receiptRepository->countAll());
        self::assertNotNull($this->receiptRepository->get($receipt->id()->toString()));

        $this->authenticate($other);
        self::assertSame(0, $this->receiptRepository->countAll());
        self::assertNull($this->receiptRepository->get($receipt->id()->toString()));
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
