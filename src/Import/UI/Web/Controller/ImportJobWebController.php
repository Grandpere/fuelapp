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

namespace App\Import\UI\Web\Controller;

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\Import\UI\Upload\BulkImportUploadProcessor;
use App\Import\UI\Upload\BulkImportUploadResult;
use App\Security\AuthenticatedUser;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ImportJobWebController extends AbstractController
{
    public function __construct(
        private readonly BulkImportUploadProcessor $bulkImportUploadProcessor,
        private readonly ImportJobRepository $importJobRepository,
    ) {
    }

    #[Route('/ui/imports', name: 'ui_import_index', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof AuthenticatedUser) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        if ('POST' === $request->getMethod()) {
            if (!$this->isCsrfTokenValid('ui_import_upload', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $uploadedFiles = $this->readUploadedFiles($request);
            if ([] === $uploadedFiles) {
                $this->addFlash('error', 'At least one file is required.');

                return $this->redirectToRoute('ui_import_index');
            }

            $result = $this->bulkImportUploadProcessor->process($user->getId()->toRfc4122(), $uploadedFiles);
            $this->storeUploadSummaryFlash($result);

            return $this->redirectToRoute('ui_import_index');
        }

        $jobs = [];
        $returnTo = $request->getRequestUri();
        foreach ($this->importJobRepository->all() as $job) {
            $jobs[] = [
                'job' => $job,
                'canAutoFinalize' => $this->canAutoFinalize($job),
                'summary' => $this->buildListSummary($job),
                'primaryAction' => $this->buildPrimaryAction($job, $returnTo),
            ];
        }

        usort(
            $jobs,
            static fn (array $a, array $b): int => $b['job']->createdAt()->getTimestamp() <=> $a['job']->createdAt()->getTimestamp(),
        );

        return $this->render('import/index.html.twig', [
            'jobs' => $jobs,
            'statusLabels' => [
                'queued' => 'Queued',
                'processing' => 'Processing',
                'needs_review' => 'Needs review',
                'failed' => 'Failed',
                'processed' => 'Processed',
                'duplicate' => 'Duplicate',
            ],
        ]);
    }

    /**
     * @return array{headline:string,detail:string}
     */
    private function buildListSummary(ImportJob $job): array
    {
        $payload = $this->decodePayload($job->errorPayload());

        return match ($job->status()) {
            ImportJobStatus::QUEUED => [
                'headline' => 'Waiting in queue',
                'detail' => 'OCR has not started yet.',
            ],
            ImportJobStatus::PROCESSING => [
                'headline' => 'OCR running',
                'detail' => 'The file is currently being parsed.',
            ],
            ImportJobStatus::NEEDS_REVIEW => [
                'headline' => $this->canAutoFinalize($job) ? 'Almost ready to finalize' : 'Manual review needed',
                'detail' => $this->readNeedsReviewDetail($payload),
            ],
            ImportJobStatus::PROCESSED => [
                'headline' => 'Receipt created',
                'detail' => null !== $this->readStringValue($payload, 'finalizedReceiptId')
                    ? 'You can open the created receipt directly.'
                    : 'The import completed successfully.',
            ],
            ImportJobStatus::DUPLICATE => [
                'headline' => 'Already handled elsewhere',
                'detail' => $this->readDuplicateDetail($payload),
            ],
            ImportJobStatus::FAILED => [
                'headline' => 'Processing stopped',
                'detail' => $this->readFailedDetail($payload, $job->errorPayload()),
            ],
        };
    }

    /**
     * @return array{label:string,url:string,variant:string}
     */
    private function buildPrimaryAction(ImportJob $job, string $returnTo): array
    {
        $payload = $this->decodePayload($job->errorPayload());
        $detailUrl = $this->generateUrl('ui_import_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);

        if ('processed' === $job->status()->value) {
            $receiptId = $this->readStringValue($payload, 'finalizedReceiptId');
            if (null !== $receiptId) {
                return [
                    'label' => 'Open receipt',
                    'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId]),
                    'variant' => 'secondary',
                ];
            }
        }

        if ('duplicate' === $job->status()->value) {
            $receiptId = $this->readStringValue($payload, 'duplicateOfReceiptId');
            if (null !== $receiptId) {
                return [
                    'label' => 'Open receipt',
                    'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId]),
                    'variant' => 'secondary',
                ];
            }

            $originalImportId = $this->readStringValue($payload, 'duplicateOfImportJobId');
            if (null !== $originalImportId) {
                return [
                    'label' => 'Open original',
                    'url' => $this->generateUrl('ui_import_show', ['id' => $originalImportId, 'return_to' => $returnTo]),
                    'variant' => 'secondary',
                ];
            }
        }

        if ('needs_review' === $job->status()->value) {
            return [
                'label' => 'Review',
                'url' => $detailUrl,
                'variant' => 'primary',
            ];
        }

        return [
            'label' => 'Detail',
            'url' => $detailUrl,
            'variant' => 'secondary',
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    private function readUploadedFiles(Request $request): array
    {
        $files = [];

        $this->collectUploadedFiles($request->files->all(), $files);

        return $files;
    }

    /** @param list<UploadedFile> $target */
    private function collectUploadedFiles(mixed $value, array &$target): void
    {
        if ($value instanceof UploadedFile) {
            $target[] = $value;

            return;
        }

        if (!is_iterable($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->collectUploadedFiles($item, $target);
        }
    }

    private function canAutoFinalize(ImportJob $job): bool
    {
        if ('needs_review' !== $job->status()->value) {
            return false;
        }

        $payload = $job->errorPayload();
        if (null === $payload || '' === trim($payload)) {
            return false;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (!is_array($decoded)) {
            return false;
        }

        if (isset($decoded['creationPayload']) && is_array($decoded['creationPayload'])) {
            return true;
        }

        $parsedDraft = $decoded['parsedDraft'] ?? null;
        if (!is_array($parsedDraft)) {
            return false;
        }

        return isset($parsedDraft['creationPayload']) && is_array($parsedDraft['creationPayload']);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readNeedsReviewDetail(?array $payload): string
    {
        if (null !== $payload) {
            $fallbackReason = $this->readStringValue($payload, 'fallbackReason');
            if (null !== $fallbackReason) {
                return sprintf('OCR fallback: %s.', $fallbackReason);
            }

            $parsedDraft = $payload['parsedDraft'] ?? null;
            if (is_array($parsedDraft) && isset($parsedDraft['issues']) && is_array($parsedDraft['issues']) && [] !== $parsedDraft['issues']) {
                $issues = array_values(array_filter($parsedDraft['issues'], static fn (mixed $issue): bool => is_string($issue) && '' !== trim($issue)));
                if ([] !== $issues) {
                    return sprintf('Check: %s.', implode(', ', array_map(static fn (string $issue): string => str_replace('_', ' ', $issue), $issues)));
                }
            }
        }

        return 'Open review to confirm the extracted fields.';
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readDuplicateDetail(?array $payload): string
    {
        $receiptId = $this->readStringValue($payload, 'duplicateOfReceiptId');
        if (null !== $receiptId) {
            return 'Matches an existing receipt.';
        }

        $importId = $this->readStringValue($payload, 'duplicateOfImportJobId');
        if (null !== $importId) {
            return 'Matches an already uploaded import.';
        }

        return 'Open the existing record instead of reviewing this file again.';
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readFailedDetail(?array $payload, ?string $rawPayload): string
    {
        $fallbackReason = $this->readStringValue($payload, 'fallbackReason');
        if (null !== $fallbackReason) {
            return sprintf('Reason: %s.', $fallbackReason);
        }

        if (is_string($rawPayload) && '' !== trim($rawPayload) && null === $payload) {
            return trim($rawPayload);
        }

        return 'Open detail to inspect the failure payload.';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(?string $payload): ?array
    {
        if (null === $payload || '' === trim($payload)) {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readStringValue(?array $payload, string $key): ?string
    {
        if (null === $payload) {
            return null;
        }

        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function storeUploadSummaryFlash(BulkImportUploadResult $result): void
    {
        $this->addFlash('import_summary', [
            'acceptedCount' => $result->acceptedCount(),
            'rejectedCount' => $result->rejectedCount(),
            'accepted' => $result->accepted(),
            'rejected' => $result->rejected(),
        ]);
    }
}
