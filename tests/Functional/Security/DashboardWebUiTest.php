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

namespace App\Tests\Functional\Security;

use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Infrastructure\Persistence\Doctrine\Entity\ImportJobEntity;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenancePlannedCostEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class DashboardWebUiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->client->disableReboot();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service is invalid.');
        }
        $this->em = $em;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_reminders, maintenance_reminder_rules, maintenance_events, maintenance_planned_costs, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testDashboardSurfacesFollowUpSignalsAndRecentActivity(): void
    {
        $email = 'dashboard.user@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($user);
        $vehicle->setName('Daily Driver');
        $vehicle->setPlateNumber('AB-123-CD');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-01 08:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-01 08:00:00'));
        $this->em->persist($vehicle);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Fuel Corner');
        $station->setStreetName('1 Main Street');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($user);
        $receipt->setVehicle($vehicle);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-27 08:15:00'));
        $receipt->setTotalCents(5400);
        $receipt->setVatAmountCents(900);
        $receipt->setOdometerKilometers(124500);
        $receiptLine = new ReceiptLineEntity();
        $receiptLine->setId(Uuid::v7());
        $receiptLine->setFuelType('diesel');
        $receiptLine->setQuantityMilliLiters(32000);
        $receiptLine->setUnitPriceDeciCentsPerLiter(1687);
        $receiptLine->setVatRatePercent(20);
        $receipt->addLine($receiptLine);
        $this->em->persist($receipt);

        $needsReview = $this->createImportJob($user, 'needs-review.jpg', ImportJobStatus::NEEDS_REVIEW, new DateTimeImmutable('2026-03-27 09:00:00'));
        $failed = $this->createImportJob($user, 'failed-upload.jpg', ImportJobStatus::FAILED, new DateTimeImmutable('2026-03-27 09:05:00'));
        $this->em->persist($needsReview);
        $this->em->persist($failed);

        $plan = new MaintenancePlannedCostEntity();
        $plan->setId(Uuid::v7());
        $plan->setOwner($user);
        $plan->setVehicle($vehicle);
        $plan->setLabel('Oil change');
        $plan->setEventType(MaintenanceEventType::SERVICE);
        $plan->setPlannedFor(new DateTimeImmutable('2026-04-02 09:00:00'));
        $plan->setPlannedCostCents(9900);
        $plan->setCurrencyCode('EUR');
        $plan->setCreatedAt(new DateTimeImmutable('2026-03-27 09:10:00'));
        $plan->setUpdatedAt(new DateTimeImmutable('2026-03-27 09:10:00'));
        $this->em->persist($plan);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $this->client->request('GET', '/ui/dashboard');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Dashboard', $content);
        self::assertStringContainsString('Needs attention now', $content);
        self::assertStringContainsString('Review import', $content);
        self::assertStringContainsString('Upload replacement', $content);
        self::assertStringContainsString('Planned maintenance is coming up', $content);
        self::assertStringContainsString('Recent receipts', $content);
        self::assertStringContainsString('Fuel Corner', $content);
        self::assertStringContainsString('Edit details', $content);
        self::assertStringContainsString('Open vehicle', $content);
        self::assertStringContainsString('Analytics', $content);
        self::assertStringContainsString('Import queue snapshot', $content);
        self::assertStringContainsString('needs-review.jpg', $content);
        self::assertStringContainsString('failed-upload.jpg', $content);
        self::assertStringContainsString('Drill down by area', $content);
        self::assertStringContainsString('Open follow-up now', $content);
        self::assertStringContainsString('Edit next plan', $content);
        self::assertStringContainsString('Open month view', $content);
        self::assertStringContainsString('/ui/imports?status=needs_review', $content);
        self::assertStringContainsString('/ui/imports?status=failed', $content);
        self::assertStringContainsString('/ui/maintenance/plans/'.$plan->getId()->toRfc4122().'/edit', $content);
        self::assertStringContainsString('return_to=', $content);
    }

    public function testDashboardEmptyStateGuidesFirstNextSteps(): void
    {
        $email = 'dashboard.empty@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $this->client->request('GET', '/ui/dashboard');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('No urgent follow-up is waiting right now.', $content);
        self::assertStringContainsString('Add first receipt', $content);
        self::assertStringContainsString('Upload first file', $content);
        self::assertStringContainsString('0 vehicles tracked so far.', $content);
        self::assertStringContainsString('Drill down by area', $content);
        self::assertStringContainsString('Open month view', $content);
    }

    private function createUser(string $email, string $password): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsActive(true);
        $this->em->persist($user);

        return $user;
    }

    private function createImportJob(UserEntity $owner, string $filename, ImportJobStatus $status, DateTimeImmutable $createdAt): ImportJobEntity
    {
        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus($status);
        $job->setStorage('local');
        $job->setFilePath('2026/03/27/'.$filename);
        $job->setOriginalFilename($filename);
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(42000);
        $job->setFileChecksumSha256(hash('sha256', $filename));
        $job->setCreatedAt($createdAt);
        $job->setUpdatedAt($createdAt);
        $job->setRetentionUntil($createdAt->modify('+30 days'));

        return $job;
    }

    private function loginWithUiForm(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/ui/login');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $form = $crawler->selectButton('Sign in')->form([
            'email' => $email,
            'password' => $password,
        ]);

        $this->client->submit($form);
        self::assertTrue($this->client->getResponse()->isRedirect());
    }
}
