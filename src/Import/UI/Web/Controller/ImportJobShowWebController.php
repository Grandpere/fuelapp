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
use App\Shared\UI\Web\SafeReturnPathResolver;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ImportJobShowWebController extends AbstractController
{
    public function __construct(
        private readonly ImportJobRepository $importJobRepository,
        private readonly SafeReturnPathResolver $safeReturnPathResolver,
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
        $creationPayload = $this->readCreationPayload($payloadData);
        $parsedDraft = $this->readParsedDraft($payloadData);
        $backToImportsUrl = $this->safeReturnPathResolver->resolve(
            $request->query->get('return_to'),
            $this->generateUrl('ui_import_index'),
        );

        return $this->render('import/show.html.twig', [
            'job' => $job,
            'backToImportsUrl' => $backToImportsUrl,
            'payloadData' => $payloadData,
            'text' => $this->readPayloadText($payloadData),
            'creationPayload' => $creationPayload,
            'parsedDraft' => $parsedDraft,
            'reviewLines' => $this->readLines($creationPayload, $parsedDraft),
            'reviewQueue' => $this->buildReviewQueue($job, $backToImportsUrl),
            'statusSummary' => $this->buildStatusSummary($job, $payloadData),
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
                'title' => 'Receipt created successfully',
                'lead' => sprintf('We already turned %s into a receipt. You can jump straight to the created record or go back to the receipt list.', $originalFilename),
                'keyDetails' => array_values(array_filter([
                    $this->detailLine('Created receipt', $this->readStringValue($payloadData, 'finalizedReceiptId')),
                    $this->detailLine('Completed at', $job->completedAt()?->format('d/m/Y H:i')),
                ])),
                'nextSteps' => [
                    'Open the created receipt if you want to double-check imported values.',
                    'Go back to the receipt list if this import no longer needs attention.',
                ],
            ],
            ImportJobStatus::DUPLICATE->value => [
                'badge' => 'duplicate',
                'title' => 'Duplicate already handled',
                'lead' => sprintf('This file (%s) matches something you already imported. Use the shortcut below to open the existing record instead of processing it again.', $originalFilename),
                'keyDetails' => array_values(array_filter([
                    $this->detailLine('Existing receipt', $this->readStringValue($payloadData, 'duplicateOfReceiptId')),
                    $this->detailLine('Original import', $this->readStringValue($payloadData, 'duplicateOfImportJobId')),
                    $this->detailLine('Reason', $this->readStringValue($payloadData, 'reason')),
                ])),
                'nextSteps' => [
                    'Open the existing receipt or original import to confirm the duplicate.',
                    'Delete this import if you do not need to keep the extra file around.',
                ],
            ],
            ImportJobStatus::FAILED->value => [
                'badge' => 'failed',
                'title' => 'Import processing stopped',
                'lead' => sprintf('We could not turn %s into a reviewable import automatically. Check the detail below, then retry or remove the file.', $originalFilename),
                'keyDetails' => array_values(array_filter([
                    $this->detailLine('Failure time', $job->failedAt()?->format('d/m/Y H:i')),
                    $this->detailLine('OCR retry count', (string) $job->ocrRetryCount()),
                    $this->detailLine('Fallback reason', $this->readStringValue($payloadData, 'fallbackReason')),
                    $this->detailLine('Raw error', is_string($job->errorPayload()) && '' !== trim($job->errorPayload()) && null === $payloadData ? trim($job->errorPayload()) : null),
                ])),
                'nextSteps' => [
                    'If the source file looks valid, retry or re-upload it after checking OCR/provider availability.',
                    'If the file is clearly unusable, delete the import to keep the queue clean.',
                ],
            ],
            ImportJobStatus::NEEDS_REVIEW->value => [
                'badge' => 'needs_review',
                'title' => 'Manual review required',
                'lead' => sprintf('OCR got close, but %s still needs a manual pass before we can create the receipt.', $originalFilename),
                'keyDetails' => array_values(array_filter([
                    $this->detailLine('Detected issues', $this->formatIssueList($payloadData)),
                    $this->detailLine('Fallback strategy', $this->readStringValue($payloadData, 'fallbackStrategy')),
                    $this->detailLine('OCR retry count', (string) $job->ocrRetryCount()),
                ])),
                'nextSteps' => [
                    'Review the extracted values below and finalize when they look right.',
                    'Use reparse if the OCR draft looks incomplete and the source file is still worth another pass.',
                ],
            ],
            default => [
                'badge' => $status,
                'title' => 'Import in progress',
                'lead' => sprintf('%s is still moving through the import pipeline. Come back in a moment if the final state is not visible yet.', $originalFilename),
                'keyDetails' => array_filter([
                    $this->detailLine('Current status', $status),
                ]),
                'nextSteps' => [
                    'Refresh later if you are waiting for OCR or queue processing to finish.',
                ],
            ],
        };
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

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
