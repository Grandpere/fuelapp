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
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Shared\UI\Web\SafeReturnPathResolver;
use App\Station\Application\Repository\StationRepository;
use App\Station\Application\Suggestion\StationSuggestion;
use App\Station\Application\Suggestion\StationSuggestionQuery;
use App\Station\Application\Suggestion\StationSuggestionReader;
use App\Vehicle\Application\Repository\VehicleRepository;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImportJobShowWebController extends AbstractController
{
    public function __construct(
        private readonly ImportJobRepository $importJobRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly StationRepository $stationRepository,
        private readonly StationSuggestionReader $stationSuggestionReader,
        private readonly SafeReturnPathResolver $safeReturnPathResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/ui/imports/{id}', name: 'ui_import_show', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['GET'])]
    public function __invoke(string $id, Request $request): Response
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $job = $this->importJobRepository->get($id);
        if (null === $job) {
            throw $this->createNotFoundException();
        }

        $payloadData = $this->decodePayload($job->errorPayload());
        $resolvedFinalizedReceiptId = $this->resolveExistingReceiptId($this->readStringValue($payloadData, 'finalizedReceiptId'));
        $resolvedDuplicateReceiptId = $this->resolveExistingReceiptId($this->readStringValue($payloadData, 'duplicateOfReceiptId'));
        $resolvedDuplicateOriginalImportId = $this->resolveExistingImportId($this->readStringValue($payloadData, 'duplicateOfImportJobId'));
        $creationPayload = $this->readCreationPayload($payloadData);
        $parsedDraft = $this->readParsedDraft($payloadData);
        $stationSuggestions = $this->stationSuggestions($creationPayload, $parsedDraft);
        $selectedSuggestion = $this->selectedSuggestionFromRequest($request, $stationSuggestions);
        $backToImportsUrl = $this->safeReturnPathResolver->resolve(
            $request->query->get('return_to'),
            $this->generateUrl('ui_import_index'),
        );

        return $this->render('import/show.html.twig', [
            'job' => $job,
            'backToImportsUrl' => $backToImportsUrl,
            'uploadShortcutUrl' => $this->generateUrl('ui_import_index').'#import-upload-card',
            'payloadData' => $payloadData,
            'text' => $this->readPayloadText($payloadData),
            'creationPayload' => $creationPayload,
            'parsedDraft' => $parsedDraft,
            'existingStationSuggestions' => array_values(array_filter($stationSuggestions, static fn (StationSuggestion $suggestion): bool => 'station' === $suggestion->sourceType)),
            'publicStationSuggestions' => array_values(array_filter($stationSuggestions, static fn (StationSuggestion $suggestion): bool => 'public' === $suggestion->sourceType)),
            'selectedSuggestionValue' => $selectedSuggestion['value'] ?? '',
            'selectedStationSuggestion' => $selectedSuggestion,
            'stationSearch' => $this->stationSearchString($creationPayload, $parsedDraft),
            'reviewLines' => $this->readLines($creationPayload, $parsedDraft),
            'resolvedFinalizedReceiptId' => $resolvedFinalizedReceiptId,
            'resolvedDuplicateReceiptId' => $resolvedDuplicateReceiptId,
            'resolvedDuplicateOriginalImportId' => $resolvedDuplicateOriginalImportId,
            'reviewQueue' => $this->buildReviewQueue($job, $backToImportsUrl),
            'statusSummary' => $this->buildStatusSummary($job, $payloadData),
            'statusActions' => $this->buildStatusActions($job, $backToImportsUrl, $payloadData),
            'receiptContinuity' => $this->buildReceiptContinuity($payloadData),
        ]);
    }

    /** @return array<string, mixed>|null */
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

    /** @param array<string, mixed>|null $payloadData
     * @return array<string, mixed>|null
     */
    private function readCreationPayload(?array $payloadData): ?array
    {
        if (null === $payloadData) {
            return null;
        }

        $root = $payloadData['creationPayload'] ?? null;
        if (is_array($root)) {
            /** @var array<string, mixed> $root */
            return $root;
        }

        $parsedDraft = $payloadData['parsedDraft'] ?? null;
        if (!is_array($parsedDraft)) {
            return null;
        }

        $nested = $parsedDraft['creationPayload'] ?? null;
        if (!is_array($nested)) {
            return null;
        }

        /** @var array<string, mixed> $nested */
        return $nested;
    }

    /** @param array<string, mixed>|null $payloadData
     * @return array<string, mixed>|null
     */
    private function readParsedDraft(?array $payloadData): ?array
    {
        if (null === $payloadData) {
            return null;
        }

        $parsedDraft = $payloadData['parsedDraft'] ?? null;
        if (!is_array($parsedDraft)) {
            return null;
        }

        /** @var array<string, mixed> $parsedDraft */
        return $parsedDraft;
    }

    /** @param array<string, mixed>|null $payloadData */
    private function readPayloadText(?array $payloadData): ?string
    {
        if (null === $payloadData) {
            return null;
        }

        $text = $payloadData['text'] ?? null;
        if (!is_string($text)) {
            return null;
        }

        $text = trim($text);

        return '' === $text ? null : $text;
    }

    /**
     * @param array<string, mixed>|null $creationPayload
     * @param array<string, mixed>|null $parsedDraft
     *
     * @return list<array<string, mixed>>
     */
    private function readLines(?array $creationPayload, ?array $parsedDraft): array
    {
        $lines = $creationPayload['lines'] ?? null;
        if (!is_array($lines) && null !== $parsedDraft) {
            $lines = $parsedDraft['lines'] ?? null;
        }

        if (!is_array($lines) || [] === $lines) {
            return [];
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            /** @var array<string, mixed> $typedLine */
            $typedLine = $line;
            $normalized[] = $typedLine;
        }

        return $normalized;
    }

    /**
     * @return array{
     *     position:int,
     *     total:int,
     *     previousUrl:?string,
     *     previousLabel:?string,
     *     nextUrl:?string,
     *     nextLabel:?string
     * }|null
     */
    private function buildReviewQueue(ImportJob $currentJob, string $returnTo): ?array
    {
        if (ImportJobStatus::NEEDS_REVIEW !== $currentJob->status()) {
            return null;
        }

        $queue = [];
        foreach ($this->importJobRepository->all() as $job) {
            if ($job->ownerId() !== $currentJob->ownerId() || ImportJobStatus::NEEDS_REVIEW !== $job->status()) {
                continue;
            }

            $queue[] = $job;
        }

        if ([] === $queue) {
            return null;
        }

        usort(
            $queue,
            static function (ImportJob $left, ImportJob $right): int {
                $createdAtOrder = $right->createdAt()->getTimestamp() <=> $left->createdAt()->getTimestamp();
                if (0 !== $createdAtOrder) {
                    return $createdAtOrder;
                }

                return strcmp($right->id()->toString(), $left->id()->toString());
            },
        );

        $currentIndex = null;
        foreach ($queue as $index => $job) {
            if ($job->id()->toString() === $currentJob->id()->toString()) {
                $currentIndex = $index;

                break;
            }
        }

        if (null === $currentIndex) {
            return null;
        }

        $previousJob = $currentIndex > 0 ? $queue[$currentIndex - 1] : null;
        $nextJob = $currentIndex < count($queue) - 1 ? $queue[$currentIndex + 1] : null;

        return [
            'position' => $currentIndex + 1,
            'total' => count($queue),
            'previousUrl' => $previousJob instanceof ImportJob
                ? $this->generateUrl('ui_import_show', ['id' => $previousJob->id()->toString(), 'return_to' => $returnTo])
                : null,
            'previousLabel' => $previousJob?->originalFilename(),
            'nextUrl' => $nextJob instanceof ImportJob
                ? $this->generateUrl('ui_import_show', ['id' => $nextJob->id()->toString(), 'return_to' => $returnTo])
                : null,
            'nextLabel' => $nextJob?->originalFilename(),
        ];
    }

    /**
     * @param array<string, mixed>|null $payloadData
     *
     * @return array{
     *     badge:string,
     *     title:string,
     *     lead:string,
     *     keyDetails:list<string>,
     *     nextSteps:list<string>
     * }
     */
    private function buildStatusSummary(ImportJob $job, ?array $payloadData): array
    {
        $status = $job->status()->value;
        $originalFilename = $job->originalFilename();

        return match ($status) {
            ImportJobStatus::PROCESSED->value => [
                'badge' => 'processed',
                'title' => $this->translator->trans('import.show.summary.processed_title'),
                'lead' => $this->translator->trans('import.show.summary.processed_lead', ['%filename%' => $originalFilename]),
                'keyDetails' => array_values(array_filter([
                    $this->detailLine($this->translator->trans('import.show.detail.created_receipt'), $this->readStringValue($payloadData, 'finalizedReceiptId')),
                    $this->detailLine($this->translator->trans('import.show.detail.completed_at'), $job->completedAt()?->format('d/m/Y H:i')),
                ])),
                'nextSteps' => [
                    $this->translator->trans('import.show.summary.processed_step_open'),
                    $this->translator->trans('import.show.summary.processed_step_back'),
                ],
            ],
            ImportJobStatus::DUPLICATE->value => [
                'badge' => 'duplicate',
                'title' => $this->translator->trans('import.show.summary.duplicate_title'),
                'lead' => $this->translator->trans('import.show.summary.duplicate_lead', ['%filename%' => $originalFilename]),
                'keyDetails' => array_values(array_filter([
                    $this->detailLine($this->translator->trans('import.show.detail.existing_receipt'), $this->readStringValue($payloadData, 'duplicateOfReceiptId')),
                    $this->detailLine($this->translator->trans('import.show.detail.original_import'), $this->readStringValue($payloadData, 'duplicateOfImportJobId')),
                    $this->detailLine($this->translator->trans('import.show.detail.reason'), $this->readStringValue($payloadData, 'reason')),
                ])),
                'nextSteps' => [
                    $this->translator->trans('import.show.summary.duplicate_step_open'),
                    $this->translator->trans('import.show.summary.duplicate_step_delete'),
                ],
            ],
            ImportJobStatus::FAILED->value => [
                'badge' => 'failed',
                'title' => $this->translator->trans('import.show.summary.failed_title'),
                'lead' => $this->translator->trans('import.show.summary.failed_lead', ['%filename%' => $originalFilename]),
                'keyDetails' => array_values(array_filter([
                    $this->detailLine($this->translator->trans('import.show.detail.failure_time'), $job->failedAt()?->format('d/m/Y H:i')),
                    $this->detailLine($this->translator->trans('import.show.detail.ocr_retry_count'), (string) $job->ocrRetryCount()),
                    $this->detailLine($this->translator->trans('import.show.detail.fallback_reason'), $this->readStringValue($payloadData, 'fallbackReason')),
                    $this->detailLine($this->translator->trans('import.show.detail.raw_error'), is_string($job->errorPayload()) && '' !== trim($job->errorPayload()) && null === $payloadData ? trim($job->errorPayload()) : null),
                ])),
                'nextSteps' => [
                    $this->translator->trans('import.show.summary.failed_step_retry'),
                    $this->translator->trans('import.show.summary.failed_step_delete'),
                ],
            ],
            ImportJobStatus::NEEDS_REVIEW->value => [
                'badge' => 'needs_review',
                'title' => $this->translator->trans('import.show.summary.needs_review_title'),
                'lead' => $this->translator->trans('import.show.summary.needs_review_lead', ['%filename%' => $originalFilename]),
                'keyDetails' => array_values(array_filter([
                    $this->detailLine($this->translator->trans('import.show.detail.detected_issues'), $this->formatIssueList($payloadData)),
                    $this->detailLine($this->translator->trans('import.show.detail.fallback_strategy'), $this->readStringValue($payloadData, 'fallbackStrategy')),
                    $this->detailLine($this->translator->trans('import.show.detail.ocr_retry_count'), (string) $job->ocrRetryCount()),
                ])),
                'nextSteps' => [
                    $this->translator->trans('import.show.summary.needs_review_step_finalize'),
                    $this->translator->trans('import.show.summary.needs_review_step_reparse'),
                ],
            ],
            default => [
                'badge' => $status,
                'title' => $this->translator->trans('import.show.summary.default_title'),
                'lead' => $this->translator->trans('import.show.summary.default_lead', ['%filename%' => $originalFilename]),
                'keyDetails' => array_filter([
                    $this->detailLine($this->translator->trans('import.show.detail.current_status'), $status),
                ]),
                'nextSteps' => [
                    $this->translator->trans('import.show.summary.default_step_refresh'),
                ],
            ],
        };
    }

    /**
     * @param array<string, mixed>|null $payloadData
     *
     * @return list<array{label:string,url:string,variant:string}>
     */
    private function buildStatusActions(ImportJob $job, string $backToImportsUrl, ?array $payloadData): array
    {
        $actions = [];
        $uploadUrl = $this->generateUrl('ui_import_index').'#import-upload-card';

        switch ($job->status()) {
            case ImportJobStatus::PROCESSED:
                $receiptId = $this->resolveExistingReceiptId($this->readStringValue($payloadData, 'finalizedReceiptId'));
                if (null !== $receiptId) {
                    $actions[] = [
                        'label' => $this->translator->trans('import.action.open_created_receipt'),
                        'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId]),
                        'variant' => 'primary',
                    ];
                }
                $actions[] = [
                    'label' => $this->translator->trans('import.action.upload_another_file'),
                    'url' => $uploadUrl,
                    'variant' => 'secondary',
                ];

                break;

            case ImportJobStatus::DUPLICATE:
                $rawReceiptId = $this->readStringValue($payloadData, 'duplicateOfReceiptId');
                $receiptId = $this->resolveExistingReceiptId($rawReceiptId);
                if (null !== $receiptId) {
                    $actions[] = [
                        'label' => $this->translator->trans('import.action.open_existing_receipt'),
                        'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId]),
                        'variant' => 'primary',
                    ];
                } else {
                    $originalImportId = $this->resolveExistingImportId($this->readStringValue($payloadData, 'duplicateOfImportJobId'));
                    if (null !== $originalImportId) {
                        $actions[] = [
                            'label' => $this->translator->trans(null !== $rawReceiptId
                                ? 'import.action.open_original_import_instead'
                                : 'import.action.open_original_import'),
                            'url' => $this->generateUrl('ui_import_show', ['id' => $originalImportId, 'return_to' => $backToImportsUrl]),
                            'variant' => 'primary',
                        ];
                    }
                }
                $actions[] = [
                    'label' => $this->translator->trans('import.action.upload_different_file'),
                    'url' => $uploadUrl,
                    'variant' => 'secondary',
                ];

                break;

            case ImportJobStatus::FAILED:
                $actions[] = [
                    'label' => $this->translator->trans('import.action.upload_replacement'),
                    'url' => $uploadUrl,
                    'variant' => 'primary',
                ];
                $actions[] = [
                    'label' => $this->translator->trans('import.action.back_to_imports'),
                    'url' => $backToImportsUrl,
                    'variant' => 'secondary',
                ];

                break;

            case ImportJobStatus::NEEDS_REVIEW:
                $actions[] = [
                    'label' => $this->translator->trans('import.action.upload_replacement'),
                    'url' => $uploadUrl,
                    'variant' => 'secondary',
                ];

                break;

            default:
                $actions[] = [
                    'label' => $this->translator->trans('import.action.back_to_imports'),
                    'url' => $backToImportsUrl,
                    'variant' => 'secondary',
                ];
        }

        return $actions;
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

        return null === $this->importJobRepository->get($importId) ? null : $importId;
    }

    /**
     * @param array<string, mixed>|null $payloadData
     */
    private function formatIssueList(?array $payloadData): ?string
    {
        $parsedDraft = $this->readParsedDraft($payloadData);
        if (null === $parsedDraft) {
            return null;
        }

        $issues = $parsedDraft['issues'] ?? null;
        if (!is_array($issues) || [] === $issues) {
            return null;
        }

        $normalized = [];
        foreach ($issues as $issue) {
            if (!is_string($issue) || '' === trim($issue)) {
                continue;
            }

            $normalized[] = str_replace('_', ' ', trim($issue));
        }

        if ([] === $normalized) {
            return null;
        }

        return implode(', ', $normalized);
    }

    /**
     * @param array<string, mixed>|null $payloadData
     */
    private function readStringValue(?array $payloadData, string $key): ?string
    {
        if (null === $payloadData) {
            return null;
        }

        $value = $payloadData[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function detailLine(string $label, ?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return sprintf('%s: %s', $label, $value);
    }

    /**
     * @param array<string, mixed>|null $creationPayload
     * @param array<string, mixed>|null $parsedDraft
     *
     * @return list<StationSuggestion>
     */
    private function stationSuggestions(?array $creationPayload, ?array $parsedDraft): array
    {
        $stationName = $this->readNestedString($creationPayload, 'stationName') ?? $this->readNestedString($parsedDraft, 'stationName');
        $streetName = $this->readNestedString($creationPayload, 'stationStreetName') ?? $this->readNestedString($parsedDraft, 'stationStreetName');
        $postalCode = $this->readNestedString($creationPayload, 'stationPostalCode') ?? $this->readNestedString($parsedDraft, 'stationPostalCode');
        $city = $this->readNestedString($creationPayload, 'stationCity') ?? $this->readNestedString($parsedDraft, 'stationCity');

        if ([] === array_filter([$stationName, $streetName, $postalCode, $city], static fn (?string $value): bool => null !== $value && '' !== trim($value))) {
            return [];
        }

        return $this->stationSuggestionReader->search(new StationSuggestionQuery(
            implode(' ', array_filter([$stationName, $streetName, $postalCode, $city])),
            $stationName,
            $streetName,
            $postalCode,
            $city,
        ));
    }

    /**
     * @param array<string, mixed>|null $creationPayload
     * @param array<string, mixed>|null $parsedDraft
     */
    private function stationSearchString(?array $creationPayload, ?array $parsedDraft): string
    {
        return implode(' ', array_filter([
            $this->readNestedString($creationPayload, 'stationName') ?? $this->readNestedString($parsedDraft, 'stationName'),
            $this->readNestedString($creationPayload, 'stationStreetName') ?? $this->readNestedString($parsedDraft, 'stationStreetName'),
            $this->readNestedString($creationPayload, 'stationPostalCode') ?? $this->readNestedString($parsedDraft, 'stationPostalCode'),
            $this->readNestedString($creationPayload, 'stationCity') ?? $this->readNestedString($parsedDraft, 'stationCity'),
        ]));
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readNestedString(?array $payload, string $key): ?string
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

    /**
     * @param list<StationSuggestion> $stationSuggestions
     *
     * @return array{value:string, kind:string, title:string, meta:string}|null
     */
    private function selectedSuggestionFromRequest(Request $request, array $stationSuggestions): ?array
    {
        $selectedSuggestion = $request->query->get('selectedSuggestion');
        if (!is_string($selectedSuggestion) || '' === trim($selectedSuggestion)) {
            return null;
        }

        $selectedSuggestion = trim($selectedSuggestion);

        foreach ($stationSuggestions as $suggestion) {
            $suggestionValue = $suggestion->sourceType.':'.$suggestion->sourceId;
            if ($suggestionValue !== $selectedSuggestion) {
                continue;
            }

            return [
                'value' => $suggestionValue,
                'kind' => $suggestion->sourceType,
                'title' => $suggestion->name,
                'meta' => $this->stationSuggestionMeta($suggestion),
            ];
        }

        return null;
    }

    private function stationSuggestionMeta(StationSuggestion $suggestion): string
    {
        if ('public' === $suggestion->sourceType) {
            return trim(sprintf('%s %s', $suggestion->postalCode, $suggestion->city));
        }

        return trim(sprintf('%s, %s %s', $suggestion->streetName, $suggestion->postalCode, $suggestion->city), ' ,');
    }

    /**
     * @param array<string, mixed>|null $payloadData
     *
     * @return array{
     *     title:string,
     *     lead:string,
     *     details:list<string>,
     *     actions:list<array{label:string,url:string,variant:string}>
     * }|null
     */
    private function buildReceiptContinuity(?array $payloadData): ?array
    {
        $receiptId = $this->readStringValue($payloadData, 'finalizedReceiptId')
            ?? $this->readStringValue($payloadData, 'duplicateOfReceiptId');

        if (null === $receiptId) {
            return null;
        }

        $receipt = $this->receiptRepository->getForSystem($receiptId);
        if (null === $receipt) {
            return null;
        }

        $details = [
            sprintf('%s: %s', $this->translator->trans('import.show.continuity.issued_at'), $receipt->issuedAt()->format('d/m/Y H:i')),
            sprintf('%s: %.2f EUR', $this->translator->trans('import.show.continuity.total'), $receipt->totalCents() / 100),
        ];

        $actions = [[
            'label' => $this->translator->trans('import.action.open_receipt'),
            'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId]),
            'variant' => 'primary',
        ]];

        $vehicleId = $receipt->vehicleId()?->toString();
        if (null !== $vehicleId) {
            $vehicle = $this->vehicleRepository->get($vehicleId);
            if (null !== $vehicle) {
                $details[] = sprintf('%s: %s', $this->translator->trans('import.show.continuity.vehicle'), $vehicle->name());
                $actions[] = [
                    'label' => $this->translator->trans('import.show.continuity.open_vehicle'),
                    'url' => $this->generateUrl('ui_vehicle_show', ['id' => $vehicleId]),
                    'variant' => 'secondary',
                ];
            }
        }

        $stationId = $receipt->stationId()?->toString();
        if (null !== $stationId) {
            $station = $this->stationRepository->get($stationId);
            if (null !== $station) {
                $details[] = sprintf('%s: %s', $this->translator->trans('import.show.continuity.station'), $station->name());
                $actions[] = [
                    'label' => $this->translator->trans('import.show.continuity.open_station'),
                    'url' => $this->generateUrl('ui_station_show', ['id' => $stationId]),
                    'variant' => 'secondary',
                ];
            }
        }

        return [
            'title' => $this->translator->trans('import.show.continuity.title'),
            'lead' => $this->translator->trans('import.show.continuity.lead'),
            'details' => $details,
            'actions' => $actions,
        ];
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
