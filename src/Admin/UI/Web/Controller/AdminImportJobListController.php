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

namespace App\Admin\UI\Web\Controller;

use App\Admin\Application\User\AdminUserManager;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\Receipt\Application\Repository\ReceiptRepository;
use DateTimeImmutable;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminImportJobListController extends AbstractController
{
    public function __construct(
        private readonly AdminUserManager $userManager,
        private readonly ImportJobRepository $importJobRepository,
        private readonly ReceiptRepository $receiptRepository,
    ) {
    }

    #[Route('/ui/admin/imports', name: 'ui_admin_import_job_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $status = $this->readStatusFilter($request, 'status');
        $ownerId = $this->readStringFilter($request, 'ownerId');
        $source = $this->readStringFilter($request, 'source');
        $query = $this->readStringFilter($request, 'q');
        $createdFrom = $this->readDateFilter($request, 'createdFrom');
        $createdTo = $this->readDateFilter($request, 'createdTo');

        $rows = [];
        $metrics = [
            ImportJobStatus::QUEUED->value => 0,
            ImportJobStatus::PROCESSING->value => 0,
            ImportJobStatus::NEEDS_REVIEW->value => 0,
            ImportJobStatus::FAILED->value => 0,
            ImportJobStatus::PROCESSED->value => 0,
            ImportJobStatus::DUPLICATE->value => 0,
        ];

        foreach ($this->importJobRepository->allForSystem() as $job) {
            ++$metrics[$job->status()->value];

            if (null !== $status && $job->status() !== $status) {
                continue;
            }

            if (null !== $ownerId && $job->ownerId() !== $ownerId) {
                continue;
            }

            if (null !== $source && mb_strtolower($job->storage()) !== mb_strtolower($source)) {
                continue;
            }

            if (null !== $query && !$this->matchesQuery($job, $query)) {
                continue;
            }

            if (null !== $createdFrom && $job->createdAt() < $createdFrom->setTime(0, 0, 0)) {
                continue;
            }

            if (null !== $createdTo && $job->createdAt() > $createdTo->setTime(23, 59, 59)) {
                continue;
            }

            $rows[] = $this->buildListRow($job, $request->getRequestUri());
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => $right['job']->createdAt()->getTimestamp() <=> $left['job']->createdAt()->getTimestamp(),
        );

        $statusFilter = $status?->value;
        $ownerOptions = [];
        foreach ($this->userManager->listUsers() as $user) {
            $ownerOptions[] = [
                'id' => $user->id,
                'label' => $user->email,
            ];
        }

        return $this->render('admin/imports/index.html.twig', [
            'jobs' => $rows,
            'metrics' => $metrics,
            'ownerOptions' => $ownerOptions,
            'filters' => [
                'status' => $statusFilter,
                'ownerId' => $ownerId,
                'source' => $source,
                'q' => $query,
                'createdFrom' => $createdFrom?->format('Y-m-d'),
                'createdTo' => $createdTo?->format('Y-m-d'),
            ],
            'activeFilterSummary' => $this->buildActiveFilterSummary($statusFilter, $ownerId, $source, $query, $createdFrom, $createdTo),
            'statusQuickFilters' => $this->buildStatusQuickFilters($request, $metrics, $statusFilter),
            'followUpShortcuts' => $this->buildFollowUpShortcuts($rows, $request->getRequestUri()),
            'statusOptions' => array_map(static fn (ImportJobStatus $jobStatus): string => $jobStatus->value, ImportJobStatus::cases()),
        ]);
    }

    /**
     * @return array{
     *     job:ImportJob,
     *     summary:array{headline:string,detail:string},
     *     decision:array{cause:string,nextStep:string},
     *     primaryAction:array{label:string,url:string,variant:string},
     *     secondaryAction:array{label:string,url:string,variant:string}|null
     * }
     */
    private function buildListRow(ImportJob $job, string $returnTo): array
    {
        $payload = $this->decodePayload($job->errorPayload());

        return [
            'job' => $job,
            'summary' => $this->buildListSummary($job, $payload),
            'decision' => $this->buildDecisionPreview($job, $payload),
            'primaryAction' => $this->buildPrimaryAction($job, $payload, $returnTo),
            'secondaryAction' => $this->buildSecondaryAction($job, $payload, $returnTo),
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{headline:string,detail:string}
     */
    private function buildListSummary(ImportJob $job, ?array $payload): array
    {
        return match ($job->status()) {
            ImportJobStatus::QUEUED => [
                'headline' => 'Waiting in queue',
                'detail' => 'Still queued before OCR starts.',
            ],
            ImportJobStatus::PROCESSING => [
                'headline' => 'OCR running',
                'detail' => 'The file is still being processed.',
            ],
            ImportJobStatus::NEEDS_REVIEW => [
                'headline' => 'Manual review needed',
                'detail' => $this->readNeedsReviewDetail($payload),
            ],
            ImportJobStatus::FAILED => [
                'headline' => 'Processing failed',
                'detail' => $this->readFailedDetail($payload, $job->errorPayload()),
            ],
            ImportJobStatus::PROCESSED => [
                'headline' => 'Receipt already created',
                'detail' => null !== $this->resolveExistingReceiptId($this->readStringValue($payload, 'finalizedReceiptId'))
                    ? 'You can jump straight to the created receipt.'
                    : 'Processing completed, but the linked receipt is no longer available.',
            ],
            ImportJobStatus::DUPLICATE => [
                'headline' => 'Duplicate already handled',
                'detail' => $this->readDuplicateDetail($payload),
            ],
        };
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{cause:string,nextStep:string}
     */
    private function buildDecisionPreview(ImportJob $job, ?array $payload): array
    {
        return match ($job->status()) {
            ImportJobStatus::NEEDS_REVIEW => [
                'cause' => $this->readNeedsReviewDetail($payload),
                'nextStep' => 'Review extracted values, fix gaps, then finalize.',
            ],
            ImportJobStatus::FAILED => [
                'cause' => $this->readFailedDetail($payload, $job->errorPayload()),
                'nextStep' => $job->ocrRetryCount() > 0
                    ? 'Retry only after checking the provider or input issue.'
                    : 'Inspect the failure details, then decide whether a retry is safe.',
            ],
            ImportJobStatus::PROCESSED => [
                'cause' => 'A receipt was already created for this import.',
                'nextStep' => 'Jump straight to the resulting receipt if support continues there.',
            ],
            ImportJobStatus::DUPLICATE => [
                'cause' => $this->readDuplicateDetail($payload),
                'nextStep' => 'Open the linked receipt or original import before taking action.',
            ],
            ImportJobStatus::PROCESSING => [
                'cause' => 'OCR is still running for this upload.',
                'nextStep' => 'Wait for a terminal state before intervening.',
            ],
            ImportJobStatus::QUEUED => [
                'cause' => 'The job has not started processing yet.',
                'nextStep' => 'Keep the queue moving unless it stalls unexpectedly.',
            ],
        };
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{label:string,url:string,variant:string}
     */
    private function buildPrimaryAction(ImportJob $job, ?array $payload, string $returnTo): array
    {
        $detailUrl = $this->generateUrl('ui_admin_import_job_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);

        return match ($job->status()) {
            ImportJobStatus::NEEDS_REVIEW => [
                'label' => 'Review',
                'url' => $detailUrl,
                'variant' => 'primary',
            ],
            ImportJobStatus::FAILED => [
                'label' => 'Inspect failure',
                'url' => $detailUrl,
                'variant' => 'secondary',
            ],
            ImportJobStatus::PROCESSED => null !== ($receiptId = $this->resolveExistingReceiptId($this->readStringValue($payload, 'finalizedReceiptId')))
                ? [
                    'label' => 'Open receipt',
                    'url' => $this->generateUrl('ui_admin_receipt_show', ['id' => $receiptId]),
                    'variant' => 'secondary',
                ]
                : [
                    'label' => 'Detail',
                    'url' => $detailUrl,
                    'variant' => 'secondary',
                ],
            ImportJobStatus::DUPLICATE => $this->buildDuplicatePrimaryAction($payload, $returnTo, $detailUrl),
            default => [
                'label' => 'Detail',
                'url' => $detailUrl,
                'variant' => 'secondary',
            ],
        };
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{label:string,url:string,variant:string}
     */
    private function buildDuplicatePrimaryAction(?array $payload, string $returnTo, string $detailUrl): array
    {
        $receiptId = $this->resolveExistingReceiptId($this->readStringValue($payload, 'duplicateOfReceiptId'));
        if (null !== $receiptId) {
            return [
                'label' => 'Open receipt',
                'url' => $this->generateUrl('ui_admin_receipt_show', ['id' => $receiptId]),
                'variant' => 'secondary',
            ];
        }

        $importId = $this->resolveExistingImportId($this->readStringValue($payload, 'duplicateOfImportJobId'));
        if (null !== $importId) {
            return [
                'label' => 'Open original',
                'url' => $this->generateUrl('ui_admin_import_job_show', ['id' => $importId, 'return_to' => $returnTo]),
                'variant' => 'secondary',
            ];
        }

        return [
            'label' => 'Detail',
            'url' => $detailUrl,
            'variant' => 'secondary',
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{label:string,url:string,variant:string}|null
     */
    private function buildSecondaryAction(ImportJob $job, ?array $payload, string $returnTo): ?array
    {
        $detailUrl = $this->generateUrl('ui_admin_import_job_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);

        return match ($job->status()) {
            ImportJobStatus::PROCESSED,
            ImportJobStatus::DUPLICATE => [
                'label' => 'Detail',
                'url' => $detailUrl,
                'variant' => 'secondary',
            ],
            ImportJobStatus::FAILED => [
                'label' => 'Detail',
                'url' => $detailUrl,
                'variant' => 'secondary',
            ],
            default => null,
        };
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function buildActiveFilterSummary(?string $status, ?string $ownerId, ?string $source, ?string $query, ?DateTimeImmutable $createdFrom, ?DateTimeImmutable $createdTo): array
    {
        $summary = [];

        if (null !== $status) {
            $summary[] = ['label' => 'Status', 'value' => $status];
        }

        if (null !== $ownerId) {
            $user = $this->userManager->getUser($ownerId);
            $summary[] = ['label' => 'Owner', 'value' => null !== $user ? sprintf('%s (%s)', $user->email, $ownerId) : $ownerId];
        }

        if (null !== $source) {
            $summary[] = ['label' => 'Source', 'value' => $source];
        }

        if (null !== $query) {
            $summary[] = ['label' => 'Search', 'value' => $query];
        }

        if (null !== $createdFrom) {
            $summary[] = ['label' => 'Created from', 'value' => $createdFrom->format('Y-m-d')];
        }

        if (null !== $createdTo) {
            $summary[] = ['label' => 'Created to', 'value' => $createdTo->format('Y-m-d')];
        }

        return $summary;
    }

    /**
     * @param array<string,int> $metrics
     *
     * @return list<array{label:string,count:int,url:string,isActive:bool}>
     */
    private function buildStatusQuickFilters(Request $request, array $metrics, ?string $statusFilter): array
    {
        $query = $request->query->all();
        unset($query['status']);

        $allCount = 0;
        foreach (ImportJobStatus::cases() as $status) {
            $allCount += $metrics[$status->value] ?? 0;
        }

        $filters = [[
            'label' => 'All',
            'count' => $allCount,
            'url' => $this->generateUrl('ui_admin_import_job_list', $query),
            'isActive' => null === $statusFilter,
        ]];

        foreach (ImportJobStatus::cases() as $status) {
            $filters[] = [
                'label' => match ($status) {
                    ImportJobStatus::NEEDS_REVIEW => 'Needs review',
                    ImportJobStatus::PROCESSED => 'Processed',
                    ImportJobStatus::PROCESSING => 'Processing',
                    ImportJobStatus::QUEUED => 'Queued',
                    ImportJobStatus::FAILED => 'Failed',
                    ImportJobStatus::DUPLICATE => 'Duplicate',
                },
                'count' => $metrics[$status->value] ?? 0,
                'url' => $this->generateUrl('ui_admin_import_job_list', [...$query, 'status' => $status->value]),
                'isActive' => $statusFilter === $status->value,
            ];
        }

        return $filters;
    }

    /**
     * @param list<array{
     *     job:ImportJob,
     *     summary:array{headline:string,detail:string},
     *     decision:array{cause:string,nextStep:string},
     *     primaryAction:array{label:string,url:string,variant:string},
     *     secondaryAction:array{label:string,url:string,variant:string}|null
     * }> $rows
     *
     * @return list<array{label:string,url:string,variant:string}>
     */
    private function buildFollowUpShortcuts(array $rows, string $returnTo): array
    {
        $shortcuts = [];

        foreach ($rows as $row) {
            if (ImportJobStatus::NEEDS_REVIEW === $row['job']->status()) {
                $shortcuts[] = [
                    'label' => 'Review next pending',
                    'url' => $this->generateUrl('ui_admin_import_job_show', ['id' => $row['job']->id()->toString(), 'return_to' => $returnTo]),
                    'variant' => 'primary',
                ];
                break;
            }
        }

        foreach ($rows as $row) {
            if (ImportJobStatus::FAILED === $row['job']->status()) {
                $shortcuts[] = [
                    'label' => 'Inspect latest failure',
                    'url' => $this->generateUrl('ui_admin_import_job_show', ['id' => $row['job']->id()->toString(), 'return_to' => $returnTo]),
                    'variant' => 'secondary',
                ];
                break;
            }
        }

        foreach ($rows as $row) {
            if (ImportJobStatus::PROCESSED === $row['job']->status() && 'Open receipt' === $row['primaryAction']['label']) {
                $shortcuts[] = [
                    'label' => 'Open latest created receipt',
                    'url' => $row['primaryAction']['url'],
                    'variant' => 'secondary',
                ];
                break;
            }
        }

        foreach ($rows as $row) {
            if (ImportJobStatus::DUPLICATE === $row['job']->status() && in_array($row['primaryAction']['label'], ['Open receipt', 'Open original'], true)) {
                $shortcuts[] = [
                    'label' => 'Check latest duplicate',
                    'url' => $row['primaryAction']['url'],
                    'variant' => 'secondary',
                ];
                break;
            }
        }

        return $shortcuts;
    }

    private function matchesQuery(ImportJob $job, string $query): bool
    {
        $needle = mb_strtolower($query);
        $haystack = mb_strtolower(sprintf(
            '%s %s %s %s %s',
            $job->id()->toString(),
            $job->ownerId(),
            $job->originalFilename(),
            $job->fileChecksumSha256(),
            $job->status()->value,
        ));

        return str_contains($haystack, $needle);
    }

    private function readStringFilter(Request $request, string $name): ?string
    {
        $raw = $request->query->get($name);
        if (!is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return '' === $value ? null : $value;
    }

    private function readStatusFilter(Request $request, string $name): ?ImportJobStatus
    {
        $value = $this->readStringFilter($request, $name);
        if (null === $value) {
            return null;
        }

        foreach (ImportJobStatus::cases() as $status) {
            if ($status->value === $value) {
                return $status;
            }
        }

        return null;
    }

    private function readDateFilter(Request $request, string $name): ?DateTimeImmutable
    {
        $value = $this->readStringFilter($request, $name);
        if (null === $value) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false === $parsed) {
            return null;
        }

        return $parsed;
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

    private function resolveExistingReceiptId(?string $receiptId): ?string
    {
        if (null === $receiptId) {
            return null;
        }

        return null === $this->receiptRepository->getForSystem($receiptId) ? null : $receiptId;
    }

    private function resolveExistingImportId(?string $importId): ?string
    {
        if (null === $importId) {
            return null;
        }

        return null === $this->importJobRepository->getForSystem($importId) ? null : $importId;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readNeedsReviewDetail(?array $payload): string
    {
        $fallbackReason = $this->readStringValue($payload, 'fallbackReason');
        if (null !== $fallbackReason) {
            return sprintf('Fallback: %s.', $fallbackReason);
        }

        $parsedDraft = $payload['parsedDraft'] ?? null;
        if (!is_array($parsedDraft)) {
            return 'Open the detail page to review and finalize the extracted values.';
        }

        $issues = $parsedDraft['issues'] ?? null;
        if (!is_array($issues) || [] === $issues) {
            return 'Open the detail page to review and finalize the extracted values.';
        }

        $normalized = [];
        foreach ($issues as $issue) {
            if (!is_string($issue) || '' === trim($issue)) {
                continue;
            }

            $normalized[] = str_replace('_', ' ', trim($issue));
        }

        if ([] === $normalized) {
            return 'Open the detail page to review and finalize the extracted values.';
        }

        return sprintf('Check: %s.', implode(', ', $normalized));
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readDuplicateDetail(?array $payload): string
    {
        if (null !== $this->resolveExistingReceiptId($this->readStringValue($payload, 'duplicateOfReceiptId'))) {
            return 'Matches an existing receipt that can still be opened.';
        }

        if (null !== $this->resolveExistingImportId($this->readStringValue($payload, 'duplicateOfImportJobId'))) {
            return 'Matches an older import that still exists.';
        }

        return 'The duplicate target no longer exists, so use detail for triage or cleanup.';
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

        return 'Open detail to inspect the failure payload and retry options.';
    }
}
