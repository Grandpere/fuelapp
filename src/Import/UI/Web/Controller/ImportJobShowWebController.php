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

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
