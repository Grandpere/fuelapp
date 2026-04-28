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

use App\PublicFuelStation\Application\Repository\PublicFuelStationSyncRunRepository;
use App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity\PublicFuelStationSyncRunEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePublicFuelStationSyncRunRepository implements PublicFuelStationSyncRunRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function start(string $sourceUrl): string
    {
        $run = new PublicFuelStationSyncRunEntity($sourceUrl);
        $this->em->persist($run);
        $this->em->flush();

        return $run->getId()->toRfc4122();
    }

    public function finish(string $id, string $status, int $processedCount, int $upsertedCount, int $rejectedCount, ?string $errorMessage = null): void
    {
        if (!Uuid::isValid($id)) {
            return;
        }

        $run = $this->em->find(PublicFuelStationSyncRunEntity::class, $id);
        if (!$run instanceof PublicFuelStationSyncRunEntity) {
            return;
        }

        $run->finish($status, $processedCount, $upsertedCount, $rejectedCount, $errorMessage);
        $this->em->persist($run);
        $this->em->flush();
    }
}
