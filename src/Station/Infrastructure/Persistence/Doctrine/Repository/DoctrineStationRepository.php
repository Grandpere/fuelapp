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

namespace App\Station\Infrastructure\Persistence\Doctrine\Repository;

use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use App\Station\Domain\ValueObject\StationId;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStationRepository implements StationRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Station $station): void
    {
        $entity = $this->em->find(StationEntity::class, $station->id()->toString()) ?? new StationEntity();
        $entity->setId(Uuid::fromString($station->id()->toString()));
        $entity->setName($station->name());
        $entity->setStreetName($station->streetName());
        $entity->setPostalCode($station->postalCode());
        $entity->setCity($station->city());
        $entity->setLatitudeMicroDegrees($station->latitudeMicroDegrees());
        $entity->setLongitudeMicroDegrees($station->longitudeMicroDegrees());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function get(string $id): ?Station
    {
        $entity = $this->em->find(StationEntity::class, $id);
        if (null === $entity) {
            return null;
        }

        return Station::reconstitute(
            StationId::fromString($entity->getId()->toRfc4122()),
            $entity->getName(),
            $entity->getStreetName(),
            $entity->getPostalCode(),
            $entity->getCity(),
            $entity->getLatitudeMicroDegrees(),
            $entity->getLongitudeMicroDegrees(),
        );
    }

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station
    {
        $entity = $this->em->getRepository(StationEntity::class)->findOneBy([
            'name' => $name,
            'streetName' => $streetName,
            'postalCode' => $postalCode,
            'city' => $city,
        ]);

        if (null === $entity) {
            return null;
        }

        return Station::reconstitute(
            StationId::fromString($entity->getId()->toRfc4122()),
            $entity->getName(),
            $entity->getStreetName(),
            $entity->getPostalCode(),
            $entity->getCity(),
            $entity->getLatitudeMicroDegrees(),
            $entity->getLongitudeMicroDegrees(),
        );
    }

    public function all(): iterable
    {
        $entities = $this->em->getRepository(StationEntity::class)->findAll();
        foreach ($entities as $entity) {
            yield Station::reconstitute(
                StationId::fromString($entity->getId()->toRfc4122()),
                $entity->getName(),
                $entity->getStreetName(),
                $entity->getPostalCode(),
                $entity->getCity(),
                $entity->getLatitudeMicroDegrees(),
                $entity->getLongitudeMicroDegrees(),
            );
        }
    }
}
