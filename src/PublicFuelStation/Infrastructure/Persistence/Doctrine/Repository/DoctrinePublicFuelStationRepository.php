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

namespace App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Repository;

use App\PublicFuelStation\Application\Import\ParsedPublicFuelStation;
use App\PublicFuelStation\Application\Repository\PublicFuelStationRepository;
use App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity\PublicFuelStationEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePublicFuelStationRepository implements PublicFuelStationRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function upsert(ParsedPublicFuelStation $station): void
    {
        $entity = $this->em->getRepository(PublicFuelStationEntity::class)->findOneBy(['sourceId' => $station->sourceId]);
        if (!$entity instanceof PublicFuelStationEntity) {
            $entity = new PublicFuelStationEntity();
            $entity->setSourceId($station->sourceId);
        }

        $entity->setLatitudeMicroDegrees($station->latitudeMicroDegrees);
        $entity->setLongitudeMicroDegrees($station->longitudeMicroDegrees);
        $entity->setAddress($station->address);
        $entity->setPostalCode($station->postalCode);
        $entity->setCity($station->city);
        $entity->setPopulationKind($station->populationKind);
        $entity->setDepartment($station->department);
        $entity->setDepartmentCode($station->departmentCode);
        $entity->setRegion($station->region);
        $entity->setRegionCode($station->regionCode);
        $entity->setAutomate24($station->automate24);
        $entity->setServices($station->services);
        $entity->setFuels($station->fuels);
        $entity->setSourceUpdatedAt($station->sourceUpdatedAt);
        $entity->setImportedAt(new DateTimeImmutable());

        $this->em->persist($entity);
        $this->em->flush();
        $this->em->clear();
    }

    public function countAll(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(PublicFuelStationEntity::class, 's')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
