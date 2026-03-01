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

use App\Admin\Application\Security\SecurityActivityEntry;
use App\Admin\Application\Security\SecurityActivityReader;
use App\Admin\Infrastructure\Persistence\Doctrine\Entity\AdminAuditLogEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSecurityActivityReader implements SecurityActivityReader
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function get(string $id): ?SecurityActivityEntry
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $item = $this->em->find(AdminAuditLogEntity::class, $id);
        if (!$item instanceof AdminAuditLogEntity) {
            return null;
        }

        if (!$this->isSecurityAction($item->getAction())) {
            return null;
        }

        return new SecurityActivityEntry(
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

    public function search(?string $action, ?string $actorId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): iterable
    {
        $qb = $this->em->getRepository(AdminAuditLogEntity::class)
            ->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        $this->applySecurityActionFilter($qb, $action);

        if (null !== $actorId && Uuid::isValid($actorId)) {
            $qb->andWhere('a.actorId = :actorId')->setParameter('actorId', Uuid::fromString($actorId));
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

            yield new SecurityActivityEntry(
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

    private function applySecurityActionFilter(QueryBuilder $qb, ?string $action): void
    {
        $normalized = is_string($action) ? trim($action) : null;
        if (null !== $normalized && '' !== $normalized) {
            if (!$this->isSecurityAction($normalized)) {
                $qb->andWhere('1 = 0');

                return;
            }

            $qb->andWhere('a.action = :action')->setParameter('action', $normalized);

            return;
        }

        $qb->andWhere('(a.action LIKE :securityPrefix OR a.action LIKE :adminUserPrefix)')
            ->setParameter('securityPrefix', 'security.%')
            ->setParameter('adminUserPrefix', 'admin.user.%');
    }

    private function isSecurityAction(string $action): bool
    {
        return str_starts_with($action, 'security.') || str_starts_with($action, 'admin.user.');
    }
}
