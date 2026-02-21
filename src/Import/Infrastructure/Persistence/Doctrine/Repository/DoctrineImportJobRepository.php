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

namespace App\Import\Infrastructure\Persistence\Doctrine\Repository;

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\Import\Domain\ValueObject\ImportJobId;
use App\Import\Infrastructure\Persistence\Doctrine\Entity\ImportJobEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineImportJobRepository implements ImportJobRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function save(ImportJob $job): void
    {
        $entity = $this->em->find(ImportJobEntity::class, $job->id()->toString()) ?? new ImportJobEntity();
        $owner = $this->em->find(UserEntity::class, $job->ownerId());
        if (!$owner instanceof UserEntity) {
            return;
        }

        $entity->setId(Uuid::fromString($job->id()->toString()));
        $entity->setOwner($owner);
        $entity->setStatus($job->status());
        $entity->setStorage($job->storage());
        $entity->setFilePath($job->filePath());
        $entity->setOriginalFilename($job->originalFilename());
        $entity->setMimeType($job->mimeType());
        $entity->setFileSizeBytes($job->fileSizeBytes());
        $entity->setFileChecksumSha256($job->fileChecksumSha256());
        $entity->setErrorPayload($job->errorPayload());
        $entity->setCreatedAt($job->createdAt());
        $entity->setUpdatedAt($job->updatedAt());
        $entity->setStartedAt($job->startedAt());
        $entity->setCompletedAt($job->completedAt());
        $entity->setFailedAt($job->failedAt());
        $entity->setRetentionUntil($job->retentionUntil());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function deleteForSystem(string $id): void
    {
        if (!Uuid::isValid($id)) {
            return;
        }

        $entity = $this->em->find(ImportJobEntity::class, $id);
        if (!$entity instanceof ImportJobEntity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function get(string $id): ?ImportJob
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $qb = $this->em->getRepository(ImportJobEntity::class)->createQueryBuilder('j')
            ->andWhere('j.id = :id')
            ->setParameter('id', $id);
        $this->applyOwnedByCurrentUser($qb, 'j');

        $entity = $qb->getQuery()->getOneOrNullResult();
        if (!$entity instanceof ImportJobEntity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function getForSystem(string $id): ?ImportJob
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $entity = $this->em->find(ImportJobEntity::class, $id);
        if (!$entity instanceof ImportJobEntity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function all(): iterable
    {
        $qb = $this->em->getRepository(ImportJobEntity::class)
            ->createQueryBuilder('j')
            ->orderBy('j.createdAt', 'DESC');
        $this->applyOwnedByCurrentUser($qb, 'j');

        $items = $qb->getQuery()->getResult();
        if (!is_iterable($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item instanceof ImportJobEntity) {
                yield $this->mapEntityToDomain($item);
            }
        }
    }

    public function allForSystem(): iterable
    {
        $items = $this->em->getRepository(ImportJobEntity::class)
            ->createQueryBuilder('j')
            ->orderBy('j.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        if (!is_iterable($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item instanceof ImportJobEntity) {
                yield $this->mapEntityToDomain($item);
            }
        }
    }

    public function findLatestByOwnerAndChecksum(string $ownerId, string $checksumSha256, ?string $excludeJobId = null): ?ImportJob
    {
        if (!Uuid::isValid($ownerId) || '' === trim($checksumSha256)) {
            return null;
        }

        $qb = $this->em->getRepository(ImportJobEntity::class)->createQueryBuilder('j')
            ->andWhere('IDENTITY(j.owner) = :ownerId')
            ->andWhere('j.fileChecksumSha256 = :checksum')
            ->andWhere('j.status != :failedStatus')
            ->setParameter('ownerId', $ownerId)
            ->setParameter('checksum', $checksumSha256)
            ->setParameter('failedStatus', ImportJobStatus::FAILED)
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults(1);

        if (null !== $excludeJobId && Uuid::isValid($excludeJobId)) {
            $qb->andWhere('j.id != :excludeJobId')->setParameter('excludeJobId', $excludeJobId);
        }

        $entity = $qb->getQuery()->getOneOrNullResult();
        if (!$entity instanceof ImportJobEntity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    private function applyOwnedByCurrentUser(QueryBuilder $qb, string $alias): void
    {
        $currentUserId = $this->currentUserId();
        if (null === $currentUserId) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb->andWhere(sprintf('IDENTITY(%s.owner) = :currentUserId', $alias))
            ->setParameter('currentUserId', $currentUserId);
    }

    private function currentUserId(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof UserEntity) {
            return null;
        }

        return $user->getId()->toRfc4122();
    }

    private function mapEntityToDomain(ImportJobEntity $entity): ImportJob
    {
        return ImportJob::reconstitute(
            ImportJobId::fromString($entity->getId()->toRfc4122()),
            $entity->getOwner()->getId()->toRfc4122(),
            $entity->getStatus(),
            $entity->getStorage(),
            $entity->getFilePath(),
            $entity->getOriginalFilename(),
            $entity->getMimeType(),
            $entity->getFileSizeBytes(),
            $entity->getFileChecksumSha256(),
            $entity->getErrorPayload(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
            $entity->getStartedAt(),
            $entity->getCompletedAt(),
            $entity->getFailedAt(),
            $entity->getRetentionUntil(),
        );
    }
}
