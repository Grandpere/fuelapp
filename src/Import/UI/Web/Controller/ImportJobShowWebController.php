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
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ImportJobShowWebController extends AbstractController
{
    public function __construct(private readonly ImportJobRepository $importJobRepository)
    {
    }

    #[Route('/ui/imports/{id}', name: 'ui_import_show', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['GET'])]
    public function __invoke(string $id): Response
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

        return $this->render('import/show.html.twig', [
            'job' => $job,
            'payloadData' => $payloadData,
            'creationPayload' => $creationPayload,
            'parsedDraft' => $parsedDraft,
            'firstLine' => $this->readFirstLine($creationPayload, $parsedDraft),
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

    /**
     * @param array<string, mixed>|null $creationPayload
     * @param array<string, mixed>|null $parsedDraft
     *
     * @return array<string, mixed>|null
     */
    private function readFirstLine(?array $creationPayload, ?array $parsedDraft): ?array
    {
        $lines = $creationPayload['lines'] ?? null;
        if (!is_array($lines) && null !== $parsedDraft) {
            $lines = $parsedDraft['lines'] ?? null;
        }

        if (!is_array($lines) || [] === $lines) {
            return null;
        }

        $first = $lines[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        /** @var array<string, mixed> $first */
        return $first;
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
