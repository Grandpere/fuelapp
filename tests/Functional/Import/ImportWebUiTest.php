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

namespace App\Tests\Functional\Import;

use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Infrastructure\Persistence\Doctrine\Entity\ImportJobEntity;
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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class ImportWebUiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private string $importStorageDir;
    private InMemoryTransport $asyncTransport;

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

        $parameterBag = $container->get(ParameterBagInterface::class);
        if (!$parameterBag instanceof ParameterBagInterface) {
            throw new RuntimeException('Parameter bag service is invalid.');
        }
        $importStorageDir = $parameterBag->get('app.import.storage_dir');
        if (!is_string($importStorageDir) || '' === trim($importStorageDir)) {
            throw new RuntimeException('Import storage parameter is invalid.');
        }
        $this->importStorageDir = $importStorageDir;

        $transport = $container->get('messenger.transport.async');
        if (!$transport instanceof InMemoryTransport) {
            throw new RuntimeException('Async transport is not in-memory in test env.');
        }
        $this->asyncTransport = $transport;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
        $filesystem = new Filesystem();
        if ($filesystem->exists($this->importStorageDir)) {
            $filesystem->remove($this->importStorageDir);
        }
        $this->asyncTransport->reset();
    }

    public function testUserCanUploadFromUiAndSeeQueuedImportInList(): void
    {
        $email = 'import.web.user@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $pageResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $pageResponse->getStatusCode());
        $pageContent = (string) $pageResponse->getContent();
        self::assertStringContainsString('Import Receipts', $pageContent);

        preg_match('/name="_token" value="([^"]+)"/', $pageContent, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p9x8AAAAASUVORK5CYII=',
            true,
        );
        if (!is_string($png)) {
            throw new RuntimeException('Unable to build PNG fixture.');
        }

        $uploadResponse = $this->request(
            'POST',
            '/ui/imports',
            ['_token' => $csrfToken],
            ['file' => $this->createUploadedFile('ticket.png', $png, 'image/png')],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_FOUND, $uploadResponse->getStatusCode());

        $listResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        $listContent = (string) $listResponse->getContent();
        self::assertStringContainsString('Last upload summary', $listContent);
        self::assertStringContainsString('1 queued, 0 rejected.', $listContent);
        self::assertStringContainsString('ticket.png', $listContent);
        self::assertStringContainsString('Queued', $listContent);
        self::assertStringContainsString('data-controller="row-link"', $listContent);

        $saved = $this->em->getRepository(ImportJobEntity::class)->findOneBy(['originalFilename' => 'ticket.png']);
        self::assertInstanceOf(ImportJobEntity::class, $saved);
    }

    public function testUserCanUploadMultipleFilesFromUiInSingleSubmit(): void
    {
        $email = 'import.web.multi@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $pageResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $pageResponse->getStatusCode());
        $pageContent = (string) $pageResponse->getContent();

        preg_match('/name="_token" value="([^"]+)"/', $pageContent, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p9x8AAAAASUVORK5CYII=',
            true,
        );
        if (!is_string($png)) {
            throw new RuntimeException('Unable to build PNG fixture.');
        }

        $uploadResponse = $this->request(
            'POST',
            '/ui/imports',
            ['_token' => $csrfToken],
            [
                'files' => [
                    $this->createUploadedFile('ticket-a.png', $png, 'image/png'),
                    $this->createUploadedFile('ticket-b.png', $png, 'image/png'),
                ],
            ],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_FOUND, $uploadResponse->getStatusCode());

        $listResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        $listContent = (string) $listResponse->getContent();
        self::assertStringContainsString('ticket-a.png', $listContent);
        self::assertStringContainsString('ticket-b.png', $listContent);

        $savedA = $this->em->getRepository(ImportJobEntity::class)->findOneBy(['originalFilename' => 'ticket-a.png']);
        $savedB = $this->em->getRepository(ImportJobEntity::class)->findOneBy(['originalFilename' => 'ticket-b.png']);
        self::assertInstanceOf(ImportJobEntity::class, $savedA);
        self::assertInstanceOf(ImportJobEntity::class, $savedB);
    }

    public function testImportListShowsConfidenceSignalsAndPrimaryActions(): void
    {
        $email = 'import.web.list.signals@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($user);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-27 08:00:00'));
        $receipt->setTotalCents(4200);
        $receipt->setVatAmountCents(700);
        $this->em->persist($receipt);

        $processed = new ImportJobEntity();
        $processed->setId(Uuid::v7());
        $processed->setOwner($user);
        $processed->setStatus(ImportJobStatus::PROCESSED);
        $processed->setStorage('local');
        $processed->setFilePath('2026/03/27/processed-list.jpg');
        $processed->setOriginalFilename('processed-list.jpg');
        $processed->setMimeType('image/jpeg');
        $processed->setFileSizeBytes(64000);
        $processed->setFileChecksumSha256(str_repeat('p', 64));
        $processed->setErrorPayload(json_encode([
            'status' => 'processed',
            'finalizedReceiptId' => $receipt->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $processed->setCreatedAt(new DateTimeImmutable('2026-03-27 08:01:00'));
        $processed->setUpdatedAt(new DateTimeImmutable('2026-03-27 08:01:00'));
        $processed->setCompletedAt(new DateTimeImmutable('2026-03-27 08:01:00'));
        $processed->setRetentionUntil(new DateTimeImmutable('2026-04-27 08:01:00'));
        $this->em->persist($processed);

        $duplicate = new ImportJobEntity();
        $duplicate->setId(Uuid::v7());
        $duplicate->setOwner($user);
        $duplicate->setStatus(ImportJobStatus::DUPLICATE);
        $duplicate->setStorage('local');
        $duplicate->setFilePath('2026/03/27/duplicate-list.jpg');
        $duplicate->setOriginalFilename('duplicate-list.jpg');
        $duplicate->setMimeType('image/jpeg');
        $duplicate->setFileSizeBytes(64000);
        $duplicate->setFileChecksumSha256(str_repeat('d', 64));
        $duplicate->setErrorPayload(json_encode([
            'status' => 'duplicate',
            'duplicateOfReceiptId' => $receipt->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $duplicate->setCreatedAt(new DateTimeImmutable('2026-03-27 08:05:00'));
        $duplicate->setUpdatedAt(new DateTimeImmutable('2026-03-27 08:05:00'));
        $duplicate->setCompletedAt(new DateTimeImmutable('2026-03-27 08:05:00'));
        $duplicate->setRetentionUntil(new DateTimeImmutable('2026-04-27 08:05:00'));
        $this->em->persist($duplicate);

        $failed = new ImportJobEntity();
        $failed->setId(Uuid::v7());
        $failed->setOwner($user);
        $failed->setStatus(ImportJobStatus::FAILED);
        $failed->setStorage('local');
        $failed->setFilePath('2026/03/27/failed-list.jpg');
        $failed->setOriginalFilename('failed-list.jpg');
        $failed->setMimeType('image/jpeg');
        $failed->setFileSizeBytes(64000);
        $failed->setFileChecksumSha256(str_repeat('f', 64));
        $failed->setErrorPayload(json_encode([
            'fallbackReason' => 'provider unavailable',
        ], JSON_THROW_ON_ERROR));
        $failed->setCreatedAt(new DateTimeImmutable('2026-03-27 08:10:00'));
        $failed->setUpdatedAt(new DateTimeImmutable('2026-03-27 08:10:00'));
        $failed->setFailedAt(new DateTimeImmutable('2026-03-27 08:10:00'));
        $failed->setRetentionUntil(new DateTimeImmutable('2026-04-27 08:10:00'));
        $this->em->persist($failed);

        $needsReview = new ImportJobEntity();
        $needsReview->setId(Uuid::v7());
        $needsReview->setOwner($user);
        $needsReview->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $needsReview->setStorage('local');
        $needsReview->setFilePath('2026/03/27/review-list.jpg');
        $needsReview->setOriginalFilename('review-list.jpg');
        $needsReview->setMimeType('image/jpeg');
        $needsReview->setFileSizeBytes(64000);
        $needsReview->setFileChecksumSha256(str_repeat('r', 64));
        $needsReview->setErrorPayload(json_encode([
            'fallbackReason' => 'manual review required',
        ], JSON_THROW_ON_ERROR));
        $needsReview->setCreatedAt(new DateTimeImmutable('2026-03-27 08:15:00'));
        $needsReview->setUpdatedAt(new DateTimeImmutable('2026-03-27 08:15:00'));
        $needsReview->setRetentionUntil(new DateTimeImmutable('2026-04-27 08:15:00'));
        $this->em->persist($needsReview);

        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $response = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringContainsString('Receipt created', $content);
        self::assertStringContainsString('Open receipt', $content);
        self::assertStringContainsString('Already handled elsewhere', $content);
        self::assertStringContainsString('Matches an existing receipt.', $content);
        self::assertStringContainsString('Processing stopped', $content);
        self::assertStringContainsString('Reason: provider unavailable.', $content);
        self::assertStringContainsString('/ui/receipts/'.$receipt->getId()->toRfc4122(), $content);
        self::assertStringContainsString('All: 4', $content);
        self::assertStringContainsString('Processed: 1', $content);
        self::assertStringContainsString('Failed: 1', $content);
        self::assertStringContainsString('Needs review: 1', $content);
        self::assertStringContainsString('Review next pending', $content);
        self::assertStringContainsString('Inspect latest failure', $content);
        self::assertStringContainsString('Upload replacement', $content);
        self::assertStringContainsString('/ui/imports#import-upload-card', $content);
        self::assertStringContainsString('Re-upload', $content);
        self::assertStringContainsString('Detail', $content);

        $failedOnlyResponse = $this->request('GET', '/ui/imports?status=failed', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $failedOnlyResponse->getStatusCode());
        $failedOnlyContent = (string) $failedOnlyResponse->getContent();
        self::assertStringContainsString('Filtered on <strong>Failed</strong>.', $failedOnlyContent);
        self::assertStringContainsString('failed-list.jpg', $failedOnlyContent);
        self::assertStringNotContainsString('processed-list.jpg', $failedOnlyContent);
    }

    public function testUserSeesStructuredBulkUploadSummaryFromUi(): void
    {
        $email = 'import.web.bulk.summary@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $pageResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $pageResponse->getStatusCode());
        $pageContent = (string) $pageResponse->getContent();

        preg_match('/name="_token" value="([^"]+)"/', $pageContent, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p9x8AAAAASUVORK5CYII=',
            true,
        );
        if (!is_string($png)) {
            throw new RuntimeException('Unable to build PNG fixture.');
        }

        $uploadResponse = $this->request(
            'POST',
            '/ui/imports',
            ['_token' => $csrfToken],
            [
                'files' => [
                    $this->createUploadedFile('valid.png', $png, 'image/png'),
                    $this->createUploadedFile('invalid.txt', 'hello', 'text/plain'),
                ],
            ],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_FOUND, $uploadResponse->getStatusCode());

        $listResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        $listContent = (string) $listResponse->getContent();
        self::assertStringContainsString('Last upload summary', $listContent);
        self::assertStringContainsString('1 queued, 1 rejected.', $listContent);
        self::assertStringContainsString('valid.png', $listContent);
        self::assertStringContainsString('invalid.txt', $listContent);
        self::assertStringContainsString('Unsupported file type', $listContent);
    }

    public function testUserCanFinalizeNeedsReviewImportFromUi(): void
    {
        $email = 'import.web.finalize@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/02/21/to-finalize.jpg');
        $job->setOriginalFilename('to-finalize.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('a', 64));
        $job->setErrorPayload(json_encode([
            'parsedDraft' => [
                'creationPayload' => [
                    'issuedAt' => '2026-02-21T10:45:00+00:00',
                    'stationName' => 'TOTAL ENERGIES',
                    'stationStreetName' => '1 Rue de Rivoli',
                    'stationPostalCode' => '75001',
                    'stationCity' => 'Paris',
                    'odometerKilometers' => 101250,
                    'lines' => [[
                        'fuelType' => 'diesel',
                        'quantityMilliLiters' => 40000,
                        'unitPriceDeciCentsPerLiter' => 1879,
                        'vatRatePercent' => 20,
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-02-21 10:46:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-02-21 10:46:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-03-21 10:46:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $jobId = $job->getId()->toRfc4122();
        $reviewPage = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $reviewPage->getStatusCode());
        $reviewContent = (string) $reviewPage->getContent();
        self::assertStringContainsString('Fix And Finalize', $reviewContent);
        $csrfToken = $this->extractFinalizeCsrfToken($reviewContent, $jobId);

        $finalizeResponse = $this->request(
            'POST',
            '/ui/imports/'.$jobId.'/finalize',
            ['_token' => $csrfToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $finalizeResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ImportJobEntity::class, $jobId);
        self::assertInstanceOf(ImportJobEntity::class, $updated);
        self::assertSame(ImportJobStatus::PROCESSED, $updated->getStatus());
        self::assertStringContainsString('finalizedReceiptId', (string) $updated->getErrorPayload());

        $receiptCount = $this->em->getRepository(ReceiptEntity::class)->count([]);
        self::assertSame(1, $receiptCount);
        $savedReceipt = $this->em->getRepository(ReceiptEntity::class)->findOneBy([]);
        self::assertInstanceOf(ReceiptEntity::class, $savedReceipt);
        self::assertSame(101250, $savedReceipt->getOdometerKilometers());
    }

    public function testImportDetailKeepsReturnToContext(): void
    {
        $email = 'import.web.context@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/context.jpg');
        $job->setOriginalFilename('context.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(2048);
        $job->setFileChecksumSha256(str_repeat('c', 64));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 10:00:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 10:00:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 10:00:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $returnTo = '/ui/imports?summary=last';

        $detailResponse = $this->request(
            'GET',
            '/ui/imports/'.$job->getId()->toRfc4122().'?return_to='.rawurlencode($returnTo),
            [],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $content = (string) $detailResponse->getContent();
        self::assertStringContainsString('href="'.$returnTo.'"', $content);
        self::assertStringContainsString('name="_redirect" value="'.$returnTo.'"', $content);
    }

    public function testNeedsReviewDetailShowsQueueNavigationContext(): void
    {
        $email = 'import.web.queue@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);
        $this->em->persist($this->createNeedsReviewJob($user, 'older-review.jpg', '2026-03-25 08:00:00', 'q'));
        $middleJob = $this->createNeedsReviewJob($user, 'middle-review.jpg', '2026-03-25 09:00:00', 'r');
        $this->em->persist($middleJob);
        $this->em->persist($this->createNeedsReviewJob($user, 'newer-review.jpg', '2026-03-25 10:00:00', 's'));
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $returnTo = '/ui/imports?status=needs_review';

        $detailResponse = $this->request(
            'GET',
            '/ui/imports/'.$middleJob->getId()->toRfc4122().'?return_to='.rawurlencode($returnTo),
            [],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $content = (string) $detailResponse->getContent();
        self::assertStringContainsString('Review queue', $content);
        self::assertStringContainsString('Import 2 of 3', $content);
        self::assertStringContainsString('Previous: newer-review.jpg', $content);
        self::assertStringContainsString('Next: older-review.jpg', $content);
        self::assertStringContainsString('/ui/imports?status=needs_review', $content);
        self::assertStringContainsString('Finalize and open next', $content);
    }

    public function testUserCanFinalizeNeedsReviewImportWithManualCorrectionsFromUi(): void
    {
        $email = 'import.web.manual.finalize@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/02/21/manual-finalize.jpg');
        $job->setOriginalFilename('manual-finalize.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('b', 64));
        $job->setErrorPayload(json_encode([
            'parsedDraft' => [
                'issuedAt' => '2026-02-21T10:45:00+00:00',
                'stationName' => 'TOTAL ENERGIES',
                'stationStreetName' => '1 Rue de Rivoli',
                'stationPostalCode' => '75001',
                'stationCity' => 'Paris',
                'lines' => [[
                    'fuelType' => 'diesel',
                    'quantityMilliLiters' => null,
                    'unitPriceDeciCentsPerLiter' => null,
                    'vatRatePercent' => 20,
                ]],
                'creationPayload' => null,
            ],
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-02-21 10:46:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-02-21 10:46:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-03-21 10:46:00'));
        $this->em->persist($job);
        $this->em->flush();

        $jobId = $job->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($email, $password);

        $reviewPage = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $reviewPage->getStatusCode());
        $reviewContent = (string) $reviewPage->getContent();
        self::assertStringContainsString('Fix And Finalize', $reviewContent);
        $csrfToken = $this->extractFinalizeCsrfToken($reviewContent, $jobId);

        $finalizeResponse = $this->request(
            'POST',
            '/ui/imports/'.$jobId.'/finalize',
            [
                '_token' => $csrfToken,
                'issuedAt' => '2026-02-21T10:45',
                'stationName' => 'TOTAL ENERGIES',
                'stationStreetName' => '1 Rue de Rivoli',
                'stationPostalCode' => '75001',
                'stationCity' => 'Paris',
                'lineFuelType' => 'diesel',
                'lineQuantityMilliLiters' => '40000',
                'lineUnitPriceDeciCentsPerLiter' => '1879',
                'lineVatRatePercent' => '20',
                'odometerKilometers' => '152320',
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $finalizeResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ImportJobEntity::class, $jobId);
        self::assertInstanceOf(ImportJobEntity::class, $updated);
        self::assertSame(ImportJobStatus::PROCESSED, $updated->getStatus());
        self::assertStringContainsString('finalizedReceiptId', (string) $updated->getErrorPayload());

        $receiptCount = $this->em->getRepository(ReceiptEntity::class)->count([]);
        self::assertSame(1, $receiptCount);
        $savedReceipt = $this->em->getRepository(ReceiptEntity::class)->findOneBy([]);
        self::assertInstanceOf(ReceiptEntity::class, $savedReceipt);
        self::assertSame(152320, $savedReceipt->getOdometerKilometers());
    }

    public function testUserCanFinalizeNeedsReviewImportWithMultipleLinesFromUi(): void
    {
        $email = 'import.web.multiline.finalize@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/03/25/manual-multiline.jpg');
        $job->setOriginalFilename('manual-multiline.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('m', 64));
        $job->setErrorPayload(json_encode([
            'parsedDraft' => [
                'issuedAt' => '2026-03-25T11:20:00+00:00',
                'stationName' => 'TOTAL ENERGIES',
                'stationStreetName' => '1 Rue de Rivoli',
                'stationPostalCode' => '75001',
                'stationCity' => 'Paris',
                'lines' => [
                    [
                        'fuelType' => 'diesel',
                        'quantityMilliLiters' => 30000,
                        'unitPriceDeciCentsPerLiter' => 1820,
                        'vatRatePercent' => 20,
                    ],
                    [
                        'fuelType' => 'sp98',
                        'quantityMilliLiters' => 10000,
                        'unitPriceDeciCentsPerLiter' => 1940,
                        'vatRatePercent' => 20,
                    ],
                ],
                'creationPayload' => null,
            ],
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-25 11:21:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-25 11:21:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-25 11:21:00'));
        $this->em->persist($job);
        $this->em->flush();

        $jobId = $job->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($email, $password);

        $reviewPage = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $reviewPage->getStatusCode());
        $reviewContent = (string) $reviewPage->getContent();
        self::assertStringContainsString('Receipt lines', $reviewContent);
        self::assertStringContainsString('name="lines[0][fuelType]"', $reviewContent);
        self::assertStringContainsString('name="lines[1][fuelType]"', $reviewContent);
        $csrfToken = $this->extractFinalizeCsrfToken($reviewContent, $jobId);

        $finalizeResponse = $this->request(
            'POST',
            '/ui/imports/'.$jobId.'/finalize',
            [
                '_token' => $csrfToken,
                'issuedAt' => '2026-03-25T11:20',
                'stationName' => 'TOTAL ENERGIES',
                'stationStreetName' => '1 Rue de Rivoli',
                'stationPostalCode' => '75001',
                'stationCity' => 'Paris',
                'lines' => [
                    [
                        'fuelType' => 'diesel',
                        'quantityMilliLiters' => '30000',
                        'unitPriceDeciCentsPerLiter' => '1820',
                        'vatRatePercent' => '20',
                    ],
                    [
                        'fuelType' => 'sp98',
                        'quantityMilliLiters' => '10000',
                        'unitPriceDeciCentsPerLiter' => '1940',
                        'vatRatePercent' => '20',
                    ],
                ],
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $finalizeResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ImportJobEntity::class, $jobId);
        self::assertInstanceOf(ImportJobEntity::class, $updated);
        self::assertSame(ImportJobStatus::PROCESSED, $updated->getStatus());

        $savedReceipt = $this->em->getRepository(ReceiptEntity::class)->findOneBy([]);
        self::assertInstanceOf(ReceiptEntity::class, $savedReceipt);
        self::assertCount(2, $savedReceipt->getLines());
    }

    public function testUserCanFinalizeAndOpenNextNeedsReviewImportFromUi(): void
    {
        $email = 'import.web.continue@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $currentJob = $this->createNeedsReviewJob($user, 'current-review.jpg', '2026-03-25 10:00:00', 't');
        $nextJob = $this->createNeedsReviewJob($user, 'next-review.jpg', '2026-03-25 09:00:00', 'u');
        $this->em->persist($currentJob);
        $this->em->persist($nextJob);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $currentJobId = $currentJob->getId()->toRfc4122();
        $nextJobId = $nextJob->getId()->toRfc4122();
        $returnTo = '/ui/imports?status=needs_review';

        $reviewPage = $this->request(
            'GET',
            '/ui/imports/'.$currentJobId.'?return_to='.rawurlencode($returnTo),
            [],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_OK, $reviewPage->getStatusCode());
        $reviewContent = (string) $reviewPage->getContent();
        $csrfToken = $this->extractFinalizeCsrfToken($reviewContent, $currentJobId);

        $finalizeResponse = $this->request(
            'POST',
            '/ui/imports/'.$currentJobId.'/finalize',
            [
                '_token' => $csrfToken,
                '_continue' => '1',
                '_return_to' => $returnTo,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $finalizeResponse->getStatusCode());
        $location = $finalizeResponse->headers->get('Location');
        self::assertIsString($location);
        self::assertStringStartsWith('/ui/imports/'.$nextJobId.'?return_to=', $location);
        self::assertStringContainsString('status%3Dneeds_review', $location);

        $this->em->clear();
        $updated = $this->em->find(ImportJobEntity::class, $currentJobId);
        self::assertInstanceOf(ImportJobEntity::class, $updated);
        self::assertSame(ImportJobStatus::PROCESSED, $updated->getStatus());
    }

    public function testUserCanFinalizeImportUsingSelectedExistingStation(): void
    {
        $email = 'import.web.station-picker@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('TOTAL ENERGIES');
        $station->setStreetName('1 Rue de Rivoli');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $this->em->persist($station);

        $existingReceipt = new ReceiptEntity();
        $existingReceipt->setId(Uuid::v7());
        $existingReceipt->setOwner($user);
        $existingReceipt->setStation($station);
        $existingReceipt->setIssuedAt(new DateTimeImmutable('2026-03-24 08:00:00'));
        $existingReceipt->setTotalCents(3200);
        $existingReceipt->setVatAmountCents(533);
        $existingLine = new ReceiptLineEntity();
        $existingLine->setId(Uuid::v7());
        $existingLine->setFuelType('diesel');
        $existingLine->setQuantityMilliLiters(20000);
        $existingLine->setUnitPriceDeciCentsPerLiter(1600);
        $existingLine->setVatRatePercent(20);
        $existingReceipt->addLine($existingLine);
        $this->em->persist($existingReceipt);

        $job = $this->createNeedsReviewJob($user, 'picker-review.jpg', '2026-04-29 10:00:00', 'x');
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $jobId = $job->getId()->toRfc4122();

        $page = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $page->getStatusCode());
        $content = (string) $page->getContent();
        self::assertStringContainsString('Existing station', $content);
        self::assertStringContainsString('TOTAL ENERGIES - 1 Rue de Rivoli, 75001 Paris', $content);
        $csrf = $this->extractFinalizeCsrfToken($content, $jobId);

        $response = $this->request('POST', '/ui/imports/'.$jobId.'/finalize', [
            '_token' => $csrf,
            'selectedStationId' => $station->getId()->toRfc4122(),
        ], [], $sessionCookie);
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());

        $this->em->clear();
        $receipts = $this->em->getRepository(ReceiptEntity::class)->findBy(['owner' => $user], ['issuedAt' => 'DESC']);
        self::assertCount(2, $receipts);
        self::assertSame($station->getId()->toRfc4122(), $receipts[0]->getStation()?->getId()->toRfc4122());
    }

    public function testFinalizeReviewWithInvalidSelectedStationShowsValidationError(): void
    {
        $email = 'import.web.invalid.selected.station@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = $this->createNeedsReviewJob($user, 'invalid-selected-station.jpg', '2026-04-29 10:00:00', 'q');
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $jobId = $job->getId()->toRfc4122();

        $page = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $page->getStatusCode());
        $csrf = $this->extractFinalizeCsrfToken((string) $page->getContent(), $jobId);

        $response = $this->request('POST', '/ui/imports/'.$jobId.'/finalize', [
            '_token' => $csrf,
            'selectedStationId' => Uuid::v7()->toRfc4122(),
        ], [], $sessionCookie);
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/ui/imports/'.$jobId.'?return_to=/ui/imports', $response->headers->get('Location'));

        $follow = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $follow->getStatusCode());
        self::assertStringContainsString('Selected station was not found.', (string) $follow->getContent());
    }

    public function testUserCanDeleteOwnImportFromUiList(): void
    {
        $email = 'import.web.delete@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::FAILED);
        $job->setStorage('local');
        $job->setFilePath('2026/03/20/to-delete.jpg');
        $job->setOriginalFilename('to-delete.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('d', 64));
        $job->setErrorPayload('{"error":"ocr failed"}');
        $job->setCreatedAt(new DateTimeImmutable('2026-03-20 10:46:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-20 10:46:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-20 10:46:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $jobId = $job->getId()->toRfc4122();

        $listResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        $listContent = (string) $listResponse->getContent();
        self::assertStringContainsString('/ui/imports/'.$jobId, $listContent);
        self::assertStringContainsString('/ui/imports/'.$jobId.'/delete', $listContent);
        $deleteToken = $this->extractDeleteCsrfToken($listContent, $jobId);

        $deleteResponse = $this->request(
            'POST',
            '/ui/imports/'.$jobId.'/delete',
            ['_token' => $deleteToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $deleteResponse->getStatusCode());
        self::assertSame('/ui/imports', $deleteResponse->headers->get('Location'));

        $this->em->clear();
        self::assertNull($this->em->find(ImportJobEntity::class, $jobId));
    }

    public function testProcessedImportDetailShowsShortcutToCreatedReceipt(): void
    {
        $email = 'import.web.processed.shortcut@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($user);
        $vehicle->setName('Processed Car');
        $vehicle->setPlateNumber('PR-001-AA');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-26 07:30:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-26 07:30:00'));
        $this->em->persist($vehicle);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Processed Station');
        $station->setStreetName('10 Import Road');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($user);
        $receipt->setVehicle($vehicle);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-26 08:00:00'));
        $receipt->setTotalCents(4200);
        $receipt->setVatAmountCents(700);
        $this->em->persist($receipt);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(25000);
        $line->setUnitPriceDeciCentsPerLiter(1680);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::PROCESSED);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/processed-shortcut.jpg');
        $job->setOriginalFilename('processed-shortcut.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('p', 64));
        $job->setErrorPayload(json_encode([
            'status' => 'processed',
            'finalizedReceiptId' => $receipt->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 08:01:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 08:01:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 08:01:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 08:01:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $jobId = $job->getId()->toRfc4122();
        $receiptId = $receipt->getId()->toRfc4122();

        $detailResponse = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Import completed', $detailContent);
        self::assertStringContainsString('What happened', $detailContent);
        self::assertStringContainsString('Receipt created successfully', $detailContent);
        self::assertStringContainsString('What you can do now', $detailContent);
        self::assertStringContainsString('Receipt continuity', $detailContent);
        self::assertStringContainsString('Vehicle: Processed Car', $detailContent);
        self::assertStringContainsString('Station: Processed Station', $detailContent);
        self::assertStringContainsString('/ui/receipts/'.$receiptId, $detailContent);
        self::assertStringContainsString('Open created receipt', $detailContent);
        self::assertStringContainsString('Open receipt', $detailContent);
        self::assertStringContainsString('/ui/vehicles/'.$vehicle->getId()->toRfc4122(), $detailContent);
        self::assertStringContainsString('/ui/stations/'.$station->getId()->toRfc4122(), $detailContent);
        self::assertStringContainsString('Upload another file', $detailContent);
        self::assertStringContainsString('/ui/imports#import-upload-card', $detailContent);
    }

    public function testDuplicateImportDetailShowsShortcutToOriginalImport(): void
    {
        $email = 'import.web.duplicate.shortcut@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $originalJob = new ImportJobEntity();
        $originalJob->setId(Uuid::v7());
        $originalJob->setOwner($user);
        $originalJob->setStatus(ImportJobStatus::PROCESSED);
        $originalJob->setStorage('local');
        $originalJob->setFilePath('2026/03/26/original.jpg');
        $originalJob->setOriginalFilename('original.jpg');
        $originalJob->setMimeType('image/jpeg');
        $originalJob->setFileSizeBytes(64000);
        $originalJob->setFileChecksumSha256(str_repeat('o', 64));
        $originalJob->setErrorPayload('{"status":"processed"}');
        $originalJob->setCreatedAt(new DateTimeImmutable('2026-03-26 09:00:00'));
        $originalJob->setUpdatedAt(new DateTimeImmutable('2026-03-26 09:00:00'));
        $originalJob->setRetentionUntil(new DateTimeImmutable('2026-04-26 09:00:00'));
        $this->em->persist($originalJob);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::DUPLICATE);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/duplicate.jpg');
        $job->setOriginalFilename('duplicate.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('d', 64));
        $job->setErrorPayload(json_encode([
            'status' => 'duplicate',
            'duplicateOfImportJobId' => $originalJob->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 09:05:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $jobId = $job->getId()->toRfc4122();
        $originalJobId = $originalJob->getId()->toRfc4122();

        $detailResponse = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Duplicate import', $detailContent);
        self::assertStringContainsString('Duplicate already handled', $detailContent);
        self::assertStringContainsString('What you can do now', $detailContent);
        self::assertStringContainsString('/ui/imports/'.$originalJobId, $detailContent);
        self::assertStringContainsString('Open original import', $detailContent);
        self::assertStringContainsString('Upload different file', $detailContent);
        self::assertStringContainsString('/ui/imports#import-upload-card', $detailContent);
    }

    public function testDuplicateImportDetailCanShortcutToExistingReceipt(): void
    {
        $email = 'import.web.duplicate.receipt@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('PETRO EST');
        $station->setStreetName('LECLERC SEZANNE HYPER');
        $station->setPostalCode('51120');
        $station->setCity('SEZANNE');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($user);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-26 09:00:00'));
        $receipt->setTotalCents(7147);
        $receipt->setVatAmountCents(1191);
        $this->em->persist($receipt);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(38000);
        $line->setUnitPriceDeciCentsPerLiter(1881);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::DUPLICATE);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/duplicate-receipt.jpg');
        $job->setOriginalFilename('duplicate-receipt.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('x', 64));
        $job->setErrorPayload(json_encode([
            'status' => 'duplicate',
            'duplicateOfReceiptId' => $receipt->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 09:05:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $detailResponse = $this->request('GET', '/ui/imports/'.$job->getId()->toRfc4122(), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Duplicate import', $detailContent);
        self::assertStringContainsString('Duplicate already handled', $detailContent);
        self::assertStringContainsString('Receipt continuity', $detailContent);
        self::assertStringContainsString('Station: PETRO EST', $detailContent);
        self::assertStringContainsString('/ui/receipts/'.$receipt->getId()->toRfc4122(), $detailContent);
        self::assertStringContainsString('Open existing receipt', $detailContent);
        self::assertStringContainsString('Open receipt', $detailContent);
        self::assertStringContainsString('/ui/stations/'.$station->getId()->toRfc4122(), $detailContent);
        self::assertStringContainsString('Upload different file', $detailContent);
    }

    public function testDuplicateImportDetailDoesNotExposeMissingReceiptShortcut(): void
    {
        $email = 'import.web.duplicate.missing.receipt@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $originalJob = new ImportJobEntity();
        $originalJob->setId(Uuid::v7());
        $originalJob->setOwner($user);
        $originalJob->setStatus(ImportJobStatus::PROCESSED);
        $originalJob->setStorage('local');
        $originalJob->setFilePath('2026/03/26/original-missing-receipt.jpg');
        $originalJob->setOriginalFilename('original-missing-receipt.jpg');
        $originalJob->setMimeType('image/jpeg');
        $originalJob->setFileSizeBytes(64000);
        $originalJob->setFileChecksumSha256(str_repeat('m', 64));
        $originalJob->setErrorPayload('{"status":"processed"}');
        $originalJob->setCreatedAt(new DateTimeImmutable('2026-03-26 09:00:00'));
        $originalJob->setUpdatedAt(new DateTimeImmutable('2026-03-26 09:00:00'));
        $originalJob->setRetentionUntil(new DateTimeImmutable('2026-04-26 09:00:00'));
        $this->em->persist($originalJob);

        $missingReceiptId = Uuid::v7()->toRfc4122();

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::DUPLICATE);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/duplicate-missing-receipt.jpg');
        $job->setOriginalFilename('duplicate-missing-receipt.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('n', 64));
        $job->setErrorPayload(json_encode([
            'status' => 'duplicate',
            'duplicateOfReceiptId' => $missingReceiptId,
            'duplicateOfImportJobId' => $originalJob->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 09:05:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $detailResponse = $this->request('GET', '/ui/imports/'.$job->getId()->toRfc4122(), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringNotContainsString('Open existing receipt', $detailContent);
        self::assertStringContainsString('Open original import instead', $detailContent);
    }

    public function testDuplicateImportDetailDoesNotExposeMissingOriginalImportShortcut(): void
    {
        $email = 'import.web.duplicate.missing.original@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::DUPLICATE);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/duplicate-missing-original.jpg');
        $job->setOriginalFilename('duplicate-missing-original.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('y', 64));
        $job->setErrorPayload(json_encode([
            'status' => 'duplicate',
            'duplicateOfImportJobId' => Uuid::v7()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 09:05:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 09:05:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $detailResponse = $this->request('GET', '/ui/imports/'.$job->getId()->toRfc4122(), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringNotContainsString('Open original import', $detailContent);
        self::assertStringContainsString('Back to imports', $detailContent);
    }

    public function testFailedImportDetailExplainsNextSteps(): void
    {
        $email = 'import.web.failed.summary@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::FAILED);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/failed.jpg');
        $job->setOriginalFilename('failed.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('f', 64));
        $job->setErrorPayload(json_encode([
            'fallbackReason' => 'provider unavailable',
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 10:00:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 10:10:00'));
        $job->setFailedAt(new DateTimeImmutable('2026-03-26 10:10:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 10:10:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $detailResponse = $this->request('GET', '/ui/imports/'.$job->getId()->toRfc4122(), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Import processing stopped', $detailContent);
        self::assertStringContainsString('Fallback reason: provider unavailable', $detailContent);
        self::assertStringContainsString('What you can do now', $detailContent);
        self::assertStringContainsString('Upload replacement', $detailContent);
        self::assertStringContainsString('/ui/imports#import-upload-card', $detailContent);
    }

    public function testUserCanReparseNeedsReviewImportFromUi(): void
    {
        $email = 'import.web.reparse@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/03/21/reparse.jpg');
        $job->setOriginalFilename('reparse.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('f', 64));
        $job->setErrorPayload(json_encode([
            'jobId' => 'job-reparse',
            'provider' => 'ocr_space',
            'text' => "PETRO EST\nLECLERC BELLE IDEE 10100 ROMILLY SUR SEINE\nle 14/12/24 a 15:07:08\nMONTANT REEL 40,32 EUR\nCarburant = GAZOLE\n= 25,25 L\nPrix unit. = 1,597 EUR\nTVA 20,00% = 6,72 EUR",
            'pages' => [],
            'parsedDraft' => [
                'stationStreetName' => null,
                'creationPayload' => null,
            ],
            'status' => 'needs_review',
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-21 10:46:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-21 10:46:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-21 10:46:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $jobId = $job->getId()->toRfc4122();

        $detailResponse = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('/ui/imports/'.$jobId.'/reparse', $detailContent);
        $reparseToken = $this->extractReparseCsrfToken($detailContent, $jobId, false);

        $reparseResponse = $this->request(
            'POST',
            '/ui/imports/'.$jobId.'/reparse',
            ['_token' => $reparseToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $reparseResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ImportJobEntity::class, $jobId);
        self::assertInstanceOf(ImportJobEntity::class, $updated);
        $payload = json_decode((string) $updated->getErrorPayload(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertArrayHasKey('parsedDraft', $payload);
        self::assertIsArray($payload['parsedDraft']);
        self::assertSame('LECLERC BELLE IDEE', $payload['parsedDraft']['stationStreetName'] ?? null);
        self::assertArrayHasKey('creationPayload', $payload['parsedDraft']);
        self::assertIsArray($payload['parsedDraft']['creationPayload']);
        self::assertSame('LECLERC BELLE IDEE', $payload['parsedDraft']['creationPayload']['stationStreetName'] ?? null);
    }

    public function testReviewHighlightsMissingIssuedAtWhenOcrDidNotDetectDate(): void
    {
        $email = 'import.web.missing-date@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/03/24/missing-date.jpg');
        $job->setOriginalFilename('missing-date.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('b', 64));
        $job->setErrorPayload(json_encode([
            'parsedDraft' => [
                'stationName' => 'TOTAL',
                'stationStreetName' => '40 Rue Robert Schuman',
                'stationPostalCode' => 'L-5751',
                'stationCity' => 'FRISANGE',
                'issuedAt' => null,
                'lines' => [[
                    'fuelType' => 'sp98',
                    'quantityMilliLiters' => 51240,
                    'unitPriceDeciCentsPerLiter' => 1068,
                    'vatRatePercent' => 5,
                ]],
                'issues' => ['issued_at_missing'],
                'creationPayload' => null,
            ],
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-24 11:00:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-24 11:00:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-24 11:00:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $jobId = $job->getId()->toRfc4122();

        $reviewResponse = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $reviewResponse->getStatusCode());
        $reviewContent = (string) $reviewResponse->getContent();
        self::assertStringContainsString('Date required before finalization', $reviewContent);
        self::assertStringContainsString('Required for this import: OCR did not detect the receipt date.', $reviewContent);
        self::assertStringContainsString('name="issuedAt"', $reviewContent);
    }

    public function testUserCanDeleteOwnImportFromUiDetailWhenNotReviewable(): void
    {
        $email = 'import.web.detail.delete@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::FAILED);
        $job->setStorage('local');
        $job->setFilePath('2026/03/20/to-delete-from-detail.jpg');
        $job->setOriginalFilename('to-delete-from-detail.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('e', 64));
        $job->setErrorPayload('{"error":"ocr failed"}');
        $job->setCreatedAt(new DateTimeImmutable('2026-03-20 10:46:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-20 10:46:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-20 10:46:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $jobId = $job->getId()->toRfc4122();

        $detailResponse = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('/ui/imports/'.$jobId.'/delete', $detailContent);
        $deleteToken = $this->extractDeleteCsrfToken($detailContent, $jobId);

        $deleteResponse = $this->request(
            'POST',
            '/ui/imports/'.$jobId.'/delete',
            ['_token' => $deleteToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $deleteResponse->getStatusCode());
        self::assertSame('/ui/imports', $deleteResponse->headers->get('Location'));

        $this->em->clear();
        self::assertNull($this->em->find(ImportJobEntity::class, $jobId));
    }

    /**
     * @param array<string, string|int|float|bool|array<int, array<string, string>>|null> $parameters
     * @param array<string, mixed>                                                        $files
     * @param array<string, string>                                                       $cookies
     */
    private function request(string $method, string $uri, array $parameters = [], array $files = [], array $cookies = []): Response
    {
        $this->client->request($method, $uri, $parameters, $files);

        return $this->client->getResponse();
    }

    /** @return array<string, string> */
    private function loginWithUiForm(string $email, string $password): array
    {
        $loginPageResponse = $this->request('GET', '/ui/login');
        self::assertSame(Response::HTTP_OK, $loginPageResponse->getStatusCode());

        $content = (string) $loginPageResponse->getContent();
        preg_match('/name="_csrf_token" value="([^"]+)"/', $content, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);

        $loginResponse = $this->request(
            'POST',
            '/ui/login',
            [
                'email' => $email,
                'password' => $password,
                '_csrf_token' => $csrfToken,
            ],
        );

        self::assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());

        return [];
    }

    private function createUser(string $email, string $plainPassword): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->em->persist($user);

        return $user;
    }

    private function createUploadedFile(string $name, string $content, string $mimeType): UploadedFile
    {
        $path = sys_get_temp_dir().'/fuelapp-import-upload-ui-'.uniqid('', true);
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, $mimeType, null, true);
    }

    private function createNeedsReviewJob(UserEntity $user, string $filename, string $createdAt, string $checksumSeed): ImportJobEntity
    {
        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/03/25/'.$filename);
        $job->setOriginalFilename($filename);
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat($checksumSeed, 64));
        $job->setErrorPayload(json_encode([
            'parsedDraft' => [
                'creationPayload' => [
                    'issuedAt' => '2026-03-25T11:20:00+00:00',
                    'stationName' => 'TOTAL ENERGIES',
                    'stationStreetName' => '1 Rue de Rivoli',
                    'stationPostalCode' => '75001',
                    'stationCity' => 'Paris',
                    'lines' => [[
                        'fuelType' => 'diesel',
                        'quantityMilliLiters' => 30000,
                        'unitPriceDeciCentsPerLiter' => 1820,
                        'vatRatePercent' => 20,
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable($createdAt));
        $job->setUpdatedAt(new DateTimeImmutable($createdAt));
        $job->setRetentionUntil(new DateTimeImmutable($createdAt)->modify('+30 days'));

        return $job;
    }

    private function extractFinalizeCsrfToken(string $content, string $jobId): string
    {
        $pattern = '#/ui/imports/'.preg_quote($jobId, '#').'/finalize.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractDeleteCsrfToken(string $content, string $jobId): string
    {
        $pattern = '#/ui/imports/'.preg_quote($jobId, '#').'/delete.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractReparseCsrfToken(string $content, string $jobId, bool $admin): string
    {
        $prefix = $admin ? '/ui/admin/imports/' : '/ui/imports/';
        $pattern = '#'.preg_quote($prefix.$jobId.'/reparse', '#').'.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }
}
