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

use App\Station\Application\Favorite\FavoriteStationRepository;
use App\Station\Domain\Favorite\FavoriteStation;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\FavoriteStationEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineFavoriteStationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private FavoriteStationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $repository = self::getContainer()->get(FavoriteStationRepository::class);
        if (!$repository instanceof FavoriteStationRepository) {
            throw new RuntimeException('FavoriteStationRepository service not found.');
        }
        $this->repository = $repository;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE favorite_stations, stations, users RESTART IDENTITY CASCADE');
    }

    public function testItPersistsAndReadsFavoriteStation(): void
    {
        [$owner, $station] = $this->createOwnerAndStation();

        $favorite = FavoriteStation::create($owner->getId()->toRfc4122(), $station->getId()->toRfc4122());
        $this->repository->save($favorite);

        $saved = $this->repository->findByOwnerAndStation($owner->getId()->toRfc4122(), $station->getId()->toRfc4122());
        self::assertNotNull($saved);
        self::assertSame($favorite->stationId(), $saved->stationId());
        self::assertSame($favorite->ownerId(), $saved->ownerId());
    }

    public function testItReturnsFavoriteIdsForStationList(): void
    {
        [$owner, $stationA] = $this->createOwnerAndStation('owner@example.com', 'Station A');
        $stationB = $this->createStation('Station B');
        $this->em->flush();

        $this->repository->save(FavoriteStation::create($owner->getId()->toRfc4122(), $stationA->getId()->toRfc4122()));

        $favoriteIds = $this->repository->favoriteStationIds($owner->getId()->toRfc4122(), [
            $stationA->getId()->toRfc4122(),
            $stationB->getId()->toRfc4122(),
        ]);

        self::assertSame([$stationA->getId()->toRfc4122()], $favoriteIds);
    }

    public function testUniqueConstraintPreventsDuplicateFavoritePair(): void
    {
        [$owner, $station] = $this->createOwnerAndStation();

        $favoriteA = new FavoriteStationEntity();
        $favoriteA->setId(Uuid::v7());
        $favoriteA->setOwner($owner);
        $favoriteA->setStation($station);
        $favoriteA->setCreatedAt(new DateTimeImmutable('2026-04-30 12:00:00'));
        $this->em->persist($favoriteA);
        $this->em->flush();

        $favoriteB = new FavoriteStationEntity();
        $favoriteB->setId(Uuid::v7());
        $favoriteB->setOwner($owner);
        $favoriteB->setStation($station);
        $favoriteB->setCreatedAt(new DateTimeImmutable('2026-04-30 12:05:00'));
        $this->em->persist($favoriteB);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    /**
     * @return array{0: UserEntity, 1: StationEntity}
     */
    private function createOwnerAndStation(string $email = 'favorite-owner@example.com', string $stationName = 'Station Favorite'): array
    {
        $owner = new UserEntity();
        $owner->setId(Uuid::v7());
        $owner->setEmail($email);
        $owner->setRoles(['ROLE_USER']);
        $owner->setPassword('hashed');
        $owner->setIsActive(true);
        $this->em->persist($owner);

        $station = $this->createStation($stationName);
        $this->em->flush();

        return [$owner, $station];
    }

    private function createStation(string $name): StationEntity
    {
        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName($name);
        $station->setStreetName('1 Favorite Road');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $this->em->persist($station);

        return $station;
    }
}
