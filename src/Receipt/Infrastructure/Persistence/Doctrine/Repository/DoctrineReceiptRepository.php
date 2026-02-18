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

namespace App\Receipt\Infrastructure\Persistence\Doctrine\Repository;

use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\Domain\Receipt;
use App\Receipt\Domain\ReceiptLine;
use App\Receipt\Domain\ValueObject\ReceiptId;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Domain\ValueObject\StationId;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineReceiptRepository implements ReceiptRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Receipt $receipt): void
    {
        $entity = $this->em->find(ReceiptEntity::class, $receipt->id()->toString()) ?? new ReceiptEntity();
        $entity->setId(Uuid::fromString($receipt->id()->toString()));
        $entity->setIssuedAt($receipt->issuedAt());
        $entity->setTotalCents($receipt->totalCents());

        if (null !== $receipt->stationId()) {
            $stationRef = $this->em->getReference(StationEntity::class, $receipt->stationId()->toString());
            $entity->setStation($stationRef);
        } else {
            $entity->setStation(null);
        }

        $entity->clearLines();
        foreach ($receipt->lines() as $line) {
            $lineEntity = new ReceiptLineEntity();
            $lineEntity->setId(Uuid::v7());
            $lineEntity->setFuelType($line->fuelType()->value);
            $lineEntity->setQuantityMilliLiters($line->quantityMilliLiters());
            $lineEntity->setUnitPriceCentsPerLiter($line->unitPriceCentsPerLiter());
            $lineEntity->setVatRatePercent($line->vatRatePercent());
            $entity->addLine($lineEntity);
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function get(string $id): ?Receipt
    {
        $entity = $this->em->find(ReceiptEntity::class, $id);
        if (null === $entity) {
            return null;
        }

        $lines = [];
        foreach ($entity->getLines() as $lineEntity) {
            $lines[] = ReceiptLine::reconstitute(
                FuelType::from($lineEntity->getFuelType()),
                $lineEntity->getQuantityMilliLiters(),
                $lineEntity->getUnitPriceCentsPerLiter(),
                $lineEntity->getVatRatePercent(),
            );
        }

        $stationId = $entity->getStation() ? StationId::fromString($entity->getStation()->getId()->toRfc4122()) : null;

        return Receipt::reconstitute(
            ReceiptId::fromString($entity->getId()->toRfc4122()),
            $entity->getIssuedAt(),
            $lines,
            $stationId,
        );
    }

    public function all(): iterable
    {
        $entities = $this->em->getRepository(ReceiptEntity::class)->findAll();
        foreach ($entities as $entity) {
            $lines = [];
            foreach ($entity->getLines() as $lineEntity) {
                $lines[] = ReceiptLine::reconstitute(
                    FuelType::from($lineEntity->getFuelType()),
                    $lineEntity->getQuantityMilliLiters(),
                    $lineEntity->getUnitPriceCentsPerLiter(),
                    $lineEntity->getVatRatePercent(),
                );
            }

            $stationId = $entity->getStation() ? StationId::fromString($entity->getStation()->getId()->toRfc4122()) : null;

            yield Receipt::reconstitute(
                ReceiptId::fromString($entity->getId()->toRfc4122()),
                $entity->getIssuedAt(),
                $lines,
                $stationId,
            );
        }
    }
}
