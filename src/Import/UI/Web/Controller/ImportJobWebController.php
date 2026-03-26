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
        foreach ($this->importJobRepository->all() as $job) {
            $jobs[] = [
                'job' => $job,
                'canAutoFinalize' => $this->canAutoFinalize($job),
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
