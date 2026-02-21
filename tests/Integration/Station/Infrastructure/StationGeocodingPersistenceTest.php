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

use App\Station\Domain\Enum\GeocodingStatus;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class StationGeocodingPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }

        $this->em = $em;
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE stations RESTART IDENTITY CASCADE');
    }

    public function testGeocodingFieldsArePersistedAndQueryable(): void
    {
        $failedAt = new DateTimeImmutable('2026-02-20 11:00:00');

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Total');
        $station->setStreetName('Rue A');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $station->setGeocodingStatus(GeocodingStatus::FAILED);
        $station->setGeocodingFailedAt($failedAt);
        $station->setGeocodingLastError('provider timeout');

        $this->em->persist($station);
        $this->em->flush();
        $this->em->clear();

        $saved = $this->em->getRepository(StationEntity::class)->findOneBy(['name' => 'Total']);

        self::assertInstanceOf(StationEntity::class, $saved);
        self::assertSame(GeocodingStatus::FAILED, $saved->getGeocodingStatus());
        self::assertSame($failedAt->format(DATE_ATOM), $saved->getGeocodingFailedAt()?->format(DATE_ATOM));
        self::assertSame('provider timeout', $saved->getGeocodingLastError());

        $failedCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(StationEntity::class, 's')
            ->andWhere('s.geocodingStatus = :status')
            ->setParameter('status', GeocodingStatus::FAILED->value)
            ->getQuery()
            ->getSingleScalarResult();

        self::assertSame(1, $failedCount);
    }
}
