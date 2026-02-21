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

namespace App\Tests\Integration\Import\Application;

use App\Import\Application\Command\FinalizeImportJobCommand;
use App\Import\Application\Command\FinalizeImportJobHandler;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class FinalizeImportJobHandlerIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ImportJobRepository $importJobRepository;
    private FinalizeImportJobHandler $handler;
    private TokenStorageInterface $tokenStorage;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $repository = self::getContainer()->get(ImportJobRepository::class);
        if (!$repository instanceof ImportJobRepository) {
            throw new RuntimeException('ImportJobRepository service not found.');
        }
        $this->importJobRepository = $repository;

        $handler = self::getContainer()->get(FinalizeImportJobHandler::class);
        if (!$handler instanceof FinalizeImportJobHandler) {
            throw new RuntimeException('FinalizeImportJobHandler service not found.');
        }
        $this->handler = $handler;

        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        if (!$tokenStorage instanceof TokenStorageInterface) {
            throw new RuntimeException('TokenStorage service not found.');
        }
        $this->tokenStorage = $tokenStorage;

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('PasswordHasher service not found.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testHandlerFinalizesNeedsReviewAndCreatesReceipt(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail('import.finalize@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'test1234'));
        $this->em->persist($user);
        $this->em->flush();

        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $job = ImportJob::createQueued(
            $user->getId()->toRfc4122(),
            'local',
            '2026/02/21/file.pdf',
            'file.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );
        $job->markNeedsReview(json_encode([
            'creationPayload' => [
                'issuedAt' => '2026-02-21T10:00:00+00:00',
                'stationName' => 'Total',
                'stationStreetName' => '1 Rue A',
                'stationPostalCode' => '75001',
                'stationCity' => 'Paris',
                'lines' => [[
                    'fuelType' => 'diesel',
                    'quantityMilliLiters' => 10000,
                    'unitPriceDeciCentsPerLiter' => 1800,
                    'vatRatePercent' => 20,
                ]],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->importJobRepository->save($job);

        $updated = ($this->handler)(new FinalizeImportJobCommand($job->id()->toString()));

        self::assertSame(ImportJobStatus::PROCESSED, $updated->status());
        self::assertStringContainsString('finalizedReceiptId', (string) $updated->errorPayload());

        $receiptCountRaw = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM receipts');
        self::assertIsNumeric($receiptCountRaw);
        $receiptCount = (int) $receiptCountRaw;
        self::assertSame(1, $receiptCount);
    }
}
