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
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImportJobWebController extends AbstractController
{
    public function __construct(
        private readonly BulkImportUploadProcessor $bulkImportUploadProcessor,
        private readonly ImportJobRepository $importJobRepository,
        private readonly TranslatorInterface $translator,
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
                $this->addFlash('error', 'import.flash.file_required');

                return $this->redirectToRoute('ui_import_index');
            }

            $result = $this->bulkImportUploadProcessor->process($user->getId()->toRfc4122(), $uploadedFiles);
            $this->storeUploadSummaryFlash($result);

            return $this->redirectToRoute('ui_import_index');
        }

        $jobs = [];
        $returnTo = $request->getRequestUri();
        $uploadUrl = $this->generateUrl('ui_import_index').'#import-upload-card';
        $statusFilter = $this->readStatusFilter($request);
        foreach ($this->importJobRepository->all() as $job) {
            $jobs[] = [
                'job' => $job,
                'canAutoFinalize' => $this->canAutoFinalize($job),
                'summary' => $this->buildListSummary($job),
                'primaryAction' => $this->buildPrimaryAction($job, $returnTo),
                'secondaryAction' => $this->buildSecondaryAction($job, $returnTo, $uploadUrl),
            ];
        }

        usort(
            $jobs,
            static fn (array $a, array $b): int => $b['job']->createdAt()->getTimestamp() <=> $a['job']->createdAt()->getTimestamp(),
        );

        $statusCounts = $this->buildStatusCounts($jobs);
        $followUpShortcuts = $this->buildFollowUpShortcuts($jobs, $returnTo, $uploadUrl);

        if (null !== $statusFilter) {
            $jobs = array_values(array_filter(
                $jobs,
                static fn (array $row): bool => $row['job']->status()->value === $statusFilter,
            ));
        }

        return $this->render('import/index.html.twig', [
            'jobs' => $jobs,
            'statusFilter' => $statusFilter,
            'statusQuickFilters' => $this->buildStatusQuickFilters($statusCounts, $statusFilter),
            'followUpShortcuts' => $followUpShortcuts,
            'statusLabels' => [
                'queued' => $this->translator->trans('import.status.queued'),
                'processing' => $this->translator->trans('import.status.processing'),
                'needs_review' => $this->translator->trans('import.status.needs_review'),
                'failed' => $this->translator->trans('import.status.failed'),
                'processed' => $this->translator->trans('import.status.processed'),
                'duplicate' => $this->translator->trans('import.status.duplicate'),
            ],
        ]);
    }

    private function readStatusFilter(Request $request): ?string
    {
        $raw = $request->query->get('status');
        if (!is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        if ('' === $value) {
            return null;
        }

        foreach (ImportJobStatus::cases() as $status) {
            if ($status->value === $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array{headline:string,detail:string}
     */
    private function buildListSummary(ImportJob $job): array
    {
        $payload = $this->decodePayload($job->errorPayload());

        return match ($job->status()) {
            ImportJobStatus::QUEUED => [
                'headline' => $this->translator->trans('import.list.summary.queued_headline'),
                'detail' => $this->translator->trans('import.list.summary.queued_detail'),
            ],
            ImportJobStatus::PROCESSING => [
                'headline' => $this->translator->trans('import.list.summary.processing_headline'),
                'detail' => $this->translator->trans('import.list.summary.processing_detail'),
            ],
            ImportJobStatus::NEEDS_REVIEW => [
                'headline' => $this->translator->trans($this->canAutoFinalize($job)
                    ? 'import.list.summary.needs_review_auto_headline'
                    : 'import.list.summary.needs_review_headline'),
                'detail' => $this->readNeedsReviewDetail($payload),
            ],
            ImportJobStatus::PROCESSED => [
                'headline' => $this->translator->trans('import.list.summary.processed_headline'),
                'detail' => null !== $this->readStringValue($payload, 'finalizedReceiptId')
                    ? $this->translator->trans('import.list.summary.processed_detail_receipt')
                    : $this->translator->trans('import.list.summary.processed_detail'),
            ],
            ImportJobStatus::DUPLICATE => [
                'headline' => $this->translator->trans('import.list.summary.duplicate_headline'),
                'detail' => $this->readDuplicateDetail($payload),
            ],
            ImportJobStatus::FAILED => [
                'headline' => $this->translator->trans('import.list.summary.failed_headline'),
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
                    'label' => $this->translator->trans('import.action.open_receipt'),
                    'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId]),
                    'variant' => 'secondary',
                ];
            }
        }

        if ('duplicate' === $job->status()->value) {
            $receiptId = $this->readStringValue($payload, 'duplicateOfReceiptId');
            if (null !== $receiptId) {
                return [
                    'label' => $this->translator->trans('import.action.open_receipt'),
                    'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId]),
                    'variant' => 'secondary',
                ];
            }

            $originalImportId = $this->readStringValue($payload, 'duplicateOfImportJobId');
            if (null !== $originalImportId) {
                return [
                    'label' => $this->translator->trans('import.action.open_original'),
                    'url' => $this->generateUrl('ui_import_show', ['id' => $originalImportId, 'return_to' => $returnTo]),
                    'variant' => 'secondary',
                ];
            }
        }

        if ('needs_review' === $job->status()->value) {
            return [
                'label' => $this->translator->trans('import.action.review'),
                'url' => $detailUrl,
                'variant' => 'primary',
            ];
        }

        return [
            'label' => $this->translator->trans('import.action.detail'),
            'url' => $detailUrl,
            'variant' => 'secondary',
        ];
    }

    /**
     * @return array{label:string,url:string,variant:string}|null
     */
    private function buildSecondaryAction(ImportJob $job, string $returnTo, string $uploadUrl): ?array
    {
        $detailUrl = $this->generateUrl('ui_import_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);

        return match ($job->status()) {
            ImportJobStatus::PROCESSED,
            ImportJobStatus::DUPLICATE => [
                'label' => ImportJobStatus::DUPLICATE === $job->status()
                    ? $this->translator->trans('import.action.upload_another')
                    : $this->translator->trans('import.action.detail'),
                'url' => ImportJobStatus::DUPLICATE === $job->status() ? $uploadUrl : $detailUrl,
                'variant' => 'secondary',
            ],
            ImportJobStatus::FAILED => [
                'label' => $this->translator->trans('import.action.reupload'),
                'url' => $uploadUrl,
                'variant' => 'secondary',
            ],
            default => null,
        };
    }

    /**
     * @param list<array{job:ImportJob,canAutoFinalize:bool,summary:array{headline:string,detail:string},primaryAction:array{label:string,url:string,variant:string},secondaryAction:array{label:string,url:string,variant:string}|null}> $jobs
     *
     * @return array<string,int>
     */
    private function buildStatusCounts(array $jobs): array
    {
        $counts = ['all' => count($jobs)];
        foreach (ImportJobStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($jobs as $row) {
            ++$counts[$row['job']->status()->value];
        }

        return $counts;
    }

    /**
     * @param array<string,int> $statusCounts
     *
     * @return list<array{label:string,count:int,url:string,isActive:bool}>
     */
    private function buildStatusQuickFilters(array $statusCounts, ?string $statusFilter): array
    {
        $filters = [[
            'label' => $this->translator->trans('import.filter.all'),
            'count' => $statusCounts['all'] ?? 0,
            'url' => $this->generateUrl('ui_import_index'),
            'isActive' => null === $statusFilter,
        ]];

        foreach (ImportJobStatus::cases() as $status) {
            $filters[] = [
                'label' => match ($status) {
                    ImportJobStatus::NEEDS_REVIEW => $this->translator->trans('import.status.needs_review'),
                    ImportJobStatus::PROCESSED => $this->translator->trans('import.status.processed'),
                    ImportJobStatus::PROCESSING => $this->translator->trans('import.status.processing'),
                    ImportJobStatus::QUEUED => $this->translator->trans('import.status.queued'),
                    ImportJobStatus::FAILED => $this->translator->trans('import.status.failed'),
                    ImportJobStatus::DUPLICATE => $this->translator->trans('import.status.duplicate'),
                },
                'count' => $statusCounts[$status->value] ?? 0,
                'url' => $this->generateUrl('ui_import_index', ['status' => $status->value]),
                'isActive' => $statusFilter === $status->value,
            ];
        }

        return $filters;
    }

    /**
     * @param list<array{job:ImportJob,canAutoFinalize:bool,summary:array{headline:string,detail:string},primaryAction:array{label:string,url:string,variant:string},secondaryAction:array{label:string,url:string,variant:string}|null}> $jobs
     *
     * @return list<array{label:string,url:string,variant:string}>
     */
    private function buildFollowUpShortcuts(array $jobs, string $returnTo, string $uploadUrl): array
    {
        $shortcuts = [];

        foreach ($jobs as $row) {
            if (ImportJobStatus::NEEDS_REVIEW === $row['job']->status()) {
                $shortcuts[] = [
                    'label' => $this->translator->trans('import.follow_up.review_next'),
                    'url' => $this->generateUrl('ui_import_show', ['id' => $row['job']->id()->toString(), 'return_to' => $returnTo]),
                    'variant' => 'primary',
                ];
                break;
            }
        }

        foreach ($jobs as $row) {
            if (ImportJobStatus::FAILED === $row['job']->status()) {
                $shortcuts[] = [
                    'label' => $this->translator->trans('import.follow_up.inspect_failure'),
                    'url' => $this->generateUrl('ui_import_show', ['id' => $row['job']->id()->toString(), 'return_to' => $returnTo]),
                    'variant' => 'secondary',
                ];
                $shortcuts[] = [
                    'label' => $this->translator->trans('import.follow_up.upload_replacement'),
                    'url' => $uploadUrl,
                    'variant' => 'secondary',
                ];
                break;
            }
        }

        return $shortcuts;
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
                return $this->translator->trans('import.list.summary.needs_review_fallback_reason', ['%reason%' => $fallbackReason]);
            }

            $parsedDraft = $payload['parsedDraft'] ?? null;
            if (is_array($parsedDraft) && isset($parsedDraft['issues']) && is_array($parsedDraft['issues']) && [] !== $parsedDraft['issues']) {
                $issues = array_values(array_filter($parsedDraft['issues'], static fn (mixed $issue): bool => is_string($issue) && '' !== trim($issue)));
                if ([] !== $issues) {
                    return $this->translator->trans('import.list.summary.needs_review_issues', [
                        '%issues%' => implode(', ', array_map(static fn (string $issue): string => str_replace('_', ' ', $issue), $issues)),
                    ]);
                }
            }
        }

        return $this->translator->trans('import.list.summary.needs_review_default');
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readDuplicateDetail(?array $payload): string
    {
        $receiptId = $this->readStringValue($payload, 'duplicateOfReceiptId');
        if (null !== $receiptId) {
            return $this->translator->trans('import.list.summary.duplicate_receipt_detail');
        }

        $importId = $this->readStringValue($payload, 'duplicateOfImportJobId');
        if (null !== $importId) {
            return $this->translator->trans('import.list.summary.duplicate_import_detail');
        }

        return $this->translator->trans('import.list.summary.duplicate_default_detail');
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readFailedDetail(?array $payload, ?string $rawPayload): string
    {
        $fallbackReason = $this->readStringValue($payload, 'fallbackReason');
        if (null !== $fallbackReason) {
            return $this->translator->trans('import.list.summary.failed_reason', ['%reason%' => $fallbackReason]);
        }

        if (is_string($rawPayload) && '' !== trim($rawPayload) && null === $payload) {
            return trim($rawPayload);
        }

        return $this->translator->trans('import.list.summary.failed_default');
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
