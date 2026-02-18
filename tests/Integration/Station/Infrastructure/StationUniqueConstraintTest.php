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

use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class StationUniqueConstraintTest extends KernelTestCase
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

    public function testUniqueConstraintOnStationIdentity(): void
    {
        $station1 = new StationEntity();
        $station1->setId(Uuid::v7());
        $station1->setName('Total');
        $station1->setStreetName('Rue A');
        $station1->setPostalCode('75001');
        $station1->setCity('Paris');

        $this->em->persist($station1);
        $this->em->flush();

        $station2 = new StationEntity();
        $station2->setId(Uuid::v7());
        $station2->setName('Total');
        $station2->setStreetName('Rue A');
        $station2->setPostalCode('75001');
        $station2->setCity('Paris');

        $this->em->persist($station2);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }
}
