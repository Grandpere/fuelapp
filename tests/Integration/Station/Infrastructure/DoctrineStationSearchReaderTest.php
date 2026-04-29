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

namespace App\Tests\Integration\Station\Infrastructure;

use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Application\Search\StationSearchQuery;
use App\Station\Application\Search\StationSearchReader;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\Station\Infrastructure\Search\DoctrineStationSearchReader;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class DoctrineStationSearchReaderTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TokenStorageInterface $tokenStorage;
    private UserPasswordHasherInterface $passwordHasher;
    private StationSearchReader $stationSearchReader;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = self::getContainer();

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

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('PasswordHasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->stationSearchReader = new DoctrineStationSearchReader($this->em, $this->tokenStorage);

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE receipt_lines, receipts, stations, users CASCADE');
    }

    public function testSearchReturnsReadableStationsOrderedByPostalCodeCityAndTextMatch(): void
    {
        $owner = $this->createUser('station.search.owner@example.com');
        $other = $this->createUser('station.search.other@example.com');

        $visibleA = $this->createStation('PETRO EST', 'LECLERC SEZANNE HYPER', '51120', 'SEZANNE');
        $visibleB = $this->createStation('TOTAL EXPRESS', '40 Rue Robert Schuman', '51120', 'SEZANNE');
        $hidden = $this->createStation('PETRO EST', 'Rue A', '75001', 'Paris');
        $this->em->flush();

        $this->attachReceiptToStation($owner, $visibleA, 1_000);
        $this->attachReceiptToStation($owner, $visibleB, 2_000);
        $this->attachReceiptToStation($other, $hidden, 3_000);
        $this->em->flush();

        $this->authenticate($owner);

        $results = $this->stationSearchReader->search(new StationSearchQuery(
            'petro sezanne',
            'PETRO EST',
            null,
            '51120',
            'SEZANNE',
        ));

        self::assertCount(2, $results);
        self::assertSame($visibleA->getId()->toRfc4122(), $results[0]->id);
        self::assertSame($visibleB->getId()->toRfc4122(), $results[1]->id);
        self::assertNotContains($hidden->getId()->toRfc4122(), array_map(static fn ($candidate): string => $candidate->id, $results));
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

    private function createStation(string $name, string $streetName, string $postalCode, string $city): StationEntity
    {
        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName($name);
        $station->setStreetName($streetName);
        $station->setPostalCode($postalCode);
        $station->setCity($city);
        $this->em->persist($station);

        return $station;
    }

    private function attachReceiptToStation(UserEntity $owner, StationEntity $station, int $lineIdSuffix): void
    {
        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-04-29 12:00:00'));
        $receipt->setTotalCents(1_800);
        $receipt->setVatAmountCents(300);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(10_000 + $lineIdSuffix);
        $line->setUnitPriceDeciCentsPerLiter(1_800);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
    }

    private function authenticate(UserEntity $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
