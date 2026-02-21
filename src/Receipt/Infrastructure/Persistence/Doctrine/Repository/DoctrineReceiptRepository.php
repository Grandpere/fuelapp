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
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Domain\ValueObject\VehicleId;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use RuntimeException;
use Stringable;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;
use UnexpectedValueException;

final readonly class DoctrineReceiptRepository implements ReceiptRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function save(Receipt $receipt): void
    {
        $owner = $this->requireCurrentUser();
        $entity = $this->findOwnedEntityById($receipt->id()->toString()) ?? new ReceiptEntity();
        $entity->setId(Uuid::fromString($receipt->id()->toString()));
        $ownerRef = $this->em->getReference(UserEntity::class, $owner->getId()->toRfc4122());
        $entity->setOwner($ownerRef);
        $entity->setIssuedAt($receipt->issuedAt());
        $entity->setTotalCents($receipt->totalCents());
        $entity->setVatAmountCents($receipt->vatAmountCents());

        if (null !== $receipt->stationId()) {
            $stationRef = $this->em->getReference(StationEntity::class, $receipt->stationId()->toString());
            $entity->setStation($stationRef);
        } else {
            $entity->setStation(null);
        }

        if (null !== $receipt->vehicleId()) {
            $vehicle = $this->em->find(VehicleEntity::class, $receipt->vehicleId()->toString());
            if (!$vehicle instanceof VehicleEntity) {
                throw new UnexpectedValueException('Vehicle not found.');
            }

            $vehicleOwnerId = $vehicle->getOwner()?->getId()->toRfc4122();
            if (null === $vehicleOwnerId || $vehicleOwnerId !== $owner->getId()->toRfc4122()) {
                throw new UnexpectedValueException('Vehicle must belong to the current user.');
            }

            $entity->setVehicle($vehicle);
        } else {
            $entity->setVehicle(null);
        }

        $entity->clearLines();
        foreach ($receipt->lines() as $line) {
            $lineEntity = new ReceiptLineEntity();
            $lineEntity->setId(Uuid::v7());
            $lineEntity->setFuelType($line->fuelType()->value);
            $lineEntity->setQuantityMilliLiters($line->quantityMilliLiters());
            $lineEntity->setUnitPriceDeciCentsPerLiter($line->unitPriceDeciCentsPerLiter());
            $lineEntity->setVatRatePercent($line->vatRatePercent());
            $entity->addLine($lineEntity);
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function get(string $id): ?Receipt
    {
        $entity = $this->findOwnedEntityById($id);
        if (null === $entity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->findOwnedEntityById($id);
        if (null === $entity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function all(): iterable
    {
        $entities = $this->baseListQuery()->getQuery()->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof ReceiptEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function paginate(int $page, int $perPage): iterable
    {
        $safePage = max(1, $page);
        $safePerPage = max(1, $perPage);
        $offset = ($safePage - 1) * $safePerPage;

        $entities = $this->baseListQuery()
            ->setFirstResult($offset)
            ->setMaxResults($safePerPage)
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof ReceiptEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function countAll(): int
    {
        $qb = $this->em
            ->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(ReceiptEntity::class, 'r');

        $this->applyOwnerFilter($qb);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function paginateFiltered(
        int $page,
        int $perPage,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): iterable {
        $safePage = max(1, $page);
        $safePerPage = max(1, $perPage);
        $offset = ($safePage - 1) * $safePerPage;

        $sortField = match ($sortBy) {
            'total' => 'r.totalCents',
            'fuel_type' => 'rl.fuelType',
            'quantity' => 'rl.quantityMilliLiters',
            'unit_price' => 'rl.unitPriceDeciCentsPerLiter',
            'vat_rate' => 'rl.vatRatePercent',
            default => 'r.issuedAt',
        };
        $safeSortDirection = 'asc' === strtolower($sortDirection) ? 'ASC' : 'DESC';

        $qb = $this->filteredQuery($stationId, $issuedFrom, $issuedTo)
            ->orderBy($sortField, $safeSortDirection)
            ->addOrderBy('r.id', $safeSortDirection)
            ->setFirstResult($offset)
            ->setMaxResults($safePerPage);
        $this->applyLineFilters(
            $qb,
            $fuelType,
            $quantityMilliLitersMin,
            $quantityMilliLitersMax,
            $unitPriceDeciCentsPerLiterMin,
            $unitPriceDeciCentsPerLiterMax,
            $vatRatePercent,
        );

        $entities = $qb->getQuery()->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof ReceiptEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function countFiltered(
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): int {
        $qb = $this->em
            ->createQueryBuilder()
            ->select('COUNT(DISTINCT r.id)')
            ->from(ReceiptEntity::class, 'r')
            ->leftJoin('r.lines', 'rl');

        $this->applyOwnerFilter($qb);
        $this->applyFilters($qb, $stationId, $issuedFrom, $issuedTo);
        $this->applyLineFilters(
            $qb,
            $fuelType,
            $quantityMilliLitersMin,
            $quantityMilliLitersMax,
            $unitPriceDeciCentsPerLiterMin,
            $unitPriceDeciCentsPerLiterMax,
            $vatRatePercent,
        );

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function paginateFilteredListRows(
        int $page,
        int $perPage,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): array {
        $safePage = max(1, $page);
        $safePerPage = max(1, $perPage);
        $offset = ($safePage - 1) * $safePerPage;

        $sortField = match ($sortBy) {
            'total' => 'r.totalCents',
            'fuel_type' => 'rl.fuelType',
            'quantity' => 'rl.quantityMilliLiters',
            'unit_price' => 'rl.unitPriceDeciCentsPerLiter',
            'vat_rate' => 'rl.vatRatePercent',
            default => 'r.issuedAt',
        };
        $safeSortDirection = 'asc' === strtolower($sortDirection) ? 'ASC' : 'DESC';

        $rows = $this->em
            ->createQueryBuilder()
            ->select('r.id AS id, r.issuedAt AS issuedAt, r.totalCents AS totalCents, r.vatAmountCents AS vatAmountCents, s.name AS stationName, s.streetName AS stationStreetName, s.postalCode AS stationPostalCode, s.city AS stationCity, rl.fuelType AS fuelType, rl.quantityMilliLiters AS quantityMilliLiters, rl.unitPriceDeciCentsPerLiter AS unitPriceDeciCentsPerLiter, rl.vatRatePercent AS vatRatePercent')
            ->from(ReceiptEntity::class, 'r')
            ->leftJoin('r.station', 's')
            ->leftJoin('r.lines', 'rl')
            ->orderBy($sortField, $safeSortDirection)
            ->addOrderBy('r.id', $safeSortDirection)
            ->setFirstResult($offset)
            ->setMaxResults($safePerPage);

        $this->applyOwnerFilter($rows);
        $this->applyFilters($rows, $stationId, $issuedFrom, $issuedTo);
        $this->applyLineFilters(
            $rows,
            $fuelType,
            $quantityMilliLitersMin,
            $quantityMilliLitersMax,
            $unitPriceDeciCentsPerLiterMin,
            $unitPriceDeciCentsPerLiterMax,
            $vatRatePercent,
        );

        /** @var list<array{
         *     id: string,
         *     issuedAt: mixed,
         *     totalCents: mixed,
         *     vatAmountCents: mixed,
         *     stationName: ?string,
         *     stationStreetName: ?string,
         *     stationPostalCode: ?string,
         *     stationCity: ?string,
         *     fuelType: mixed,
         *     quantityMilliLiters: mixed,
         *     unitPriceDeciCentsPerLiter: mixed,
         *     vatRatePercent: mixed
         * }> $result
         */
        $result = $rows->getQuery()->getArrayResult();

        $normalized = [];
        foreach ($result as $row) {
            $normalized[] = [
                'id' => $this->toStringValue($row['id'], 'id'),
                'issuedAt' => $this->toDateTimeImmutableValue($row['issuedAt'], 'issuedAt'),
                'totalCents' => $this->toIntValue($row['totalCents'], 'totalCents'),
                'vatAmountCents' => $this->toIntValue($row['vatAmountCents'], 'vatAmountCents'),
                'stationName' => $this->toNullableStringValue($row['stationName'], 'stationName'),
                'stationStreetName' => $this->toNullableStringValue($row['stationStreetName'], 'stationStreetName'),
                'stationPostalCode' => $this->toNullableStringValue($row['stationPostalCode'], 'stationPostalCode'),
                'stationCity' => $this->toNullableStringValue($row['stationCity'], 'stationCity'),
                'fuelType' => $this->toNullableStringValue($row['fuelType'], 'fuelType'),
                'quantityMilliLiters' => $this->toNullableIntValue($row['quantityMilliLiters'], 'quantityMilliLiters'),
                'unitPriceDeciCentsPerLiter' => $this->toNullableIntValue($row['unitPriceDeciCentsPerLiter'], 'unitPriceDeciCentsPerLiter'),
                'vatRatePercent' => $this->toNullableIntValue($row['vatRatePercent'], 'vatRatePercent'),
            ];
        }

        return $normalized;
    }

    public function listFilteredRowsForExport(
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): array {
        return $this->paginateFilteredListRows(
            1,
            2000000000,
            $stationId,
            $issuedFrom,
            $issuedTo,
            $sortBy,
            $sortDirection,
            $fuelType,
            $quantityMilliLitersMin,
            $quantityMilliLitersMax,
            $unitPriceDeciCentsPerLiterMin,
            $unitPriceDeciCentsPerLiterMax,
            $vatRatePercent,
        );
    }

    private function toDateTimeImmutableValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new UnexpectedValueException(sprintf('Expected %s to be a datetime, got %s.', $field, get_debug_type($value)));
    }

    private function toIntValue(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && 1 === preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException(sprintf('Expected %s to be an int, got %s.', $field, get_debug_type($value)));
    }

    private function toStringValue(mixed $value, string $field): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new UnexpectedValueException(sprintf('Expected %s to be a string, got %s.', $field, get_debug_type($value)));
    }

    private function toNullableStringValue(mixed $value, string $field): ?string
    {
        if (null === $value) {
            return null;
        }

        return $this->toStringValue($value, $field);
    }

    private function toNullableIntValue(mixed $value, string $field): ?int
    {
        if (null === $value) {
            return null;
        }

        return $this->toIntValue($value, $field);
    }

    private function baseListQuery(): QueryBuilder
    {
        $qb = $this->em
            ->createQueryBuilder()
            ->select('r')
            ->from(ReceiptEntity::class, 'r')
            ->orderBy('r.issuedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC');

        $this->applyOwnerFilter($qb);

        return $qb;
    }

    private function filteredQuery(?string $stationId, ?DateTimeImmutable $issuedFrom, ?DateTimeImmutable $issuedTo): QueryBuilder
    {
        $qb = $this->em
            ->createQueryBuilder()
            ->select('r')
            ->from(ReceiptEntity::class, 'r')
            ->leftJoin('r.lines', 'rl');

        $this->applyOwnerFilter($qb);
        $this->applyFilters($qb, $stationId, $issuedFrom, $issuedTo);

        return $qb;
    }

    private function applyLineFilters(
        QueryBuilder $qb,
        ?string $fuelType,
        ?int $quantityMilliLitersMin,
        ?int $quantityMilliLitersMax,
        ?int $unitPriceDeciCentsPerLiterMin,
        ?int $unitPriceDeciCentsPerLiterMax,
        ?int $vatRatePercent,
    ): void {
        if (null !== $fuelType && '' !== $fuelType) {
            $qb->andWhere('rl.fuelType = :fuelType')->setParameter('fuelType', $fuelType);
        }

        if (null !== $quantityMilliLitersMin) {
            $qb->andWhere('rl.quantityMilliLiters >= :quantityMin')->setParameter('quantityMin', $quantityMilliLitersMin);
        }

        if (null !== $quantityMilliLitersMax) {
            $qb->andWhere('rl.quantityMilliLiters <= :quantityMax')->setParameter('quantityMax', $quantityMilliLitersMax);
        }

        if (null !== $unitPriceDeciCentsPerLiterMin) {
            $qb->andWhere('rl.unitPriceDeciCentsPerLiter >= :unitPriceMin')->setParameter('unitPriceMin', $unitPriceDeciCentsPerLiterMin);
        }

        if (null !== $unitPriceDeciCentsPerLiterMax) {
            $qb->andWhere('rl.unitPriceDeciCentsPerLiter <= :unitPriceMax')->setParameter('unitPriceMax', $unitPriceDeciCentsPerLiterMax);
        }

        if (null !== $vatRatePercent) {
            $qb->andWhere('rl.vatRatePercent = :vatRatePercent')->setParameter('vatRatePercent', $vatRatePercent);
        }
    }

    private function applyFilters(
        QueryBuilder $qb,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
    ): void {
        if (null !== $stationId && '' !== $stationId) {
            if (!Uuid::isValid($stationId)) {
                $qb->andWhere('1 = 0');

                return;
            }

            $qb
                ->andWhere('IDENTITY(r.station) = :stationId')
                ->setParameter('stationId', $stationId);
        }

        if (null !== $issuedFrom) {
            $qb
                ->andWhere('r.issuedAt >= :issuedFrom')
                ->setParameter('issuedFrom', $issuedFrom->setTime(0, 0, 0));
        }

        if (null !== $issuedTo) {
            $qb
                ->andWhere('r.issuedAt <= :issuedTo')
                ->setParameter('issuedTo', $issuedTo->setTime(23, 59, 59));
        }
    }

    private function mapEntityToDomain(ReceiptEntity $entity): Receipt
    {
        $lines = [];
        foreach ($entity->getLines() as $lineEntity) {
            $lines[] = ReceiptLine::reconstitute(
                FuelType::from($lineEntity->getFuelType()),
                $lineEntity->getQuantityMilliLiters(),
                $lineEntity->getUnitPriceDeciCentsPerLiter(),
                $lineEntity->getVatRatePercent(),
            );
        }

        $stationId = $entity->getStation() ? StationId::fromString($entity->getStation()->getId()->toRfc4122()) : null;
        $vehicleId = $entity->getVehicle() ? VehicleId::fromString($entity->getVehicle()->getId()->toRfc4122()) : null;

        return Receipt::reconstitute(
            ReceiptId::fromString($entity->getId()->toRfc4122()),
            $entity->getIssuedAt(),
            $lines,
            $stationId,
            $vehicleId,
        );
    }

    private function requireCurrentUser(): UserEntity
    {
        $user = $this->currentUser();
        if (null === $user) {
            throw new RuntimeException('Authenticated user is required to persist receipts.');
        }

        return $user;
    }

    private function currentUser(): ?UserEntity
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof UserEntity ? $user : null;
    }

    private function applyOwnerFilter(QueryBuilder $qb): void
    {
        $user = $this->currentUser();
        if (null === $user) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb->andWhere('IDENTITY(r.owner) = :currentOwnerId')
            ->setParameter('currentOwnerId', $user->getId()->toRfc4122());
    }

    private function findOwnedEntityById(string $id): ?ReceiptEntity
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $qb = $this->em
            ->createQueryBuilder()
            ->select('r')
            ->from(ReceiptEntity::class, 'r')
            ->andWhere('r.id = :id')
            ->setParameter('id', $id);
        $this->applyOwnerFilter($qb);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity instanceof ReceiptEntity ? $entity : null;
    }
}
