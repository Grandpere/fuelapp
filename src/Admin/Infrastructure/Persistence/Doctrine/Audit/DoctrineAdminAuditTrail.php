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

namespace App\Admin\Infrastructure\Persistence\Doctrine\Audit;

use App\Admin\Application\Audit\AdminAuditContext;
use App\Admin\Application\Audit\AdminAuditTrail;
use App\Admin\Infrastructure\Persistence\Doctrine\Entity\AdminAuditLogEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAdminAuditTrail implements AdminAuditTrail
{
    public function __construct(
        private EntityManagerInterface $em,
        private AdminAuditContext $context,
    ) {
    }

    public function record(string $action, string $targetType, string $targetId, array $diffSummary = [], array $metadata = []): void
    {
        $entry = new AdminAuditLogEntity();
        $entry->setId(Uuid::v7());

        $actorId = $this->context->actorId();
        $entry->setActorId((null !== $actorId && Uuid::isValid($actorId)) ? Uuid::fromString($actorId) : null);
        $entry->setActorEmail($this->context->actorEmail());

        $entry->setAction(trim($action));
        $entry->setTargetType(trim($targetType));
        $entry->setTargetId(trim($targetId));
        $entry->setDiffSummary($diffSummary);
        $entry->setMetadata(array_merge($this->context->metadata(), $metadata));
        $entry->setCorrelationId($this->context->correlationId());
        $entry->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($entry);
        $this->em->flush();
    }
}
