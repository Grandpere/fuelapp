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

namespace App\Admin\Infrastructure\Persistence\Doctrine\Repository;

use App\Admin\Application\Audit\AdminAuditLogEntry;
use App\Admin\Application\Audit\AdminAuditLogReader;
use App\Admin\Infrastructure\Persistence\Doctrine\Entity\AdminAuditLogEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAdminAuditLogReader implements AdminAuditLogReader
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function get(string $id): ?AdminAuditLogEntry
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $item = $this->em->find(AdminAuditLogEntity::class, $id);
        if (!$item instanceof AdminAuditLogEntity) {
            return null;
        }

        return $this->map($item);
    }

    public function search(
        ?string $action,
        ?string $actorId,
        ?string $targetType,
        ?string $targetId,
        ?string $correlationId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
    ): iterable {
        $qb = $this->em->getRepository(AdminAuditLogEntity::class)
            ->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if (null !== $action && '' !== trim($action)) {
            $qb->andWhere('a.action = :action')->setParameter('action', trim($action));
        }

        if (null !== $actorId && Uuid::isValid($actorId)) {
            $qb->andWhere('a.actorId = :actorId')->setParameter('actorId', Uuid::fromString($actorId));
        }

        if (null !== $targetType && '' !== trim($targetType)) {
            $qb->andWhere('a.targetType = :targetType')->setParameter('targetType', trim($targetType));
        }

        if (null !== $targetId && '' !== trim($targetId)) {
            $qb->andWhere('a.targetId = :targetId')->setParameter('targetId', trim($targetId));
        }

        if (null !== $correlationId && '' !== trim($correlationId)) {
            $qb->andWhere('a.correlationId = :correlationId')->setParameter('correlationId', trim($correlationId));
        }

        if (null !== $from) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $from->setTime(0, 0, 0));
        }

        if (null !== $to) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', $to->setTime(23, 59, 59));
        }

        $items = $qb->getQuery()->getResult();
        if (!is_iterable($items)) {
            return;
        }

        foreach ($items as $item) {
            if (!$item instanceof AdminAuditLogEntity) {
                continue;
            }

            yield $this->map($item);
        }
    }

    private function map(AdminAuditLogEntity $item): AdminAuditLogEntry
    {
        return new AdminAuditLogEntry(
            $item->getId()->toRfc4122(),
            $item->getActorId()?->toRfc4122(),
            $item->getActorEmail(),
            $item->getAction(),
            $item->getTargetType(),
            $item->getTargetId(),
            $item->getDiffSummary(),
            $item->getMetadata(),
            $item->getCorrelationId(),
            $item->getCreatedAt(),
        );
    }
}
