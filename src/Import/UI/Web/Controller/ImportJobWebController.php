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

use App\Import\Application\Command\CreateImportJobCommand;
use App\Import\Application\Command\CreateImportJobHandler;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\ImportJob;
use App\Security\AuthenticatedUser;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ImportJobWebController extends AbstractController
{
    // OCR.Space free tier hard limit.
    private const MAX_UPLOAD_SIZE = '1024K';

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly CreateImportJobHandler $createImportJobHandler,
        private readonly ImportJobRepository $importJobRepository,
        private readonly ValidatorInterface $validator,
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

            $uploadedFile = $request->files->get('file');
            if (!$uploadedFile instanceof UploadedFile) {
                $this->addFlash('error', 'File is required.');

                return $this->redirectToRoute('ui_import_index');
            }

            $violations = $this->validator->validate($uploadedFile, [
                new Assert\File(
                    maxSize: self::MAX_UPLOAD_SIZE,
                    mimeTypes: self::ALLOWED_MIME_TYPES,
                    maxSizeMessage: 'File is too large. Current import limit is 1 MB.',
                    mimeTypesMessage: 'Unsupported file type. Allowed: PDF, JPEG, PNG, WEBP.',
                ),
            ]);

            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('error', (string) $violation->getMessage());
                }

                return $this->redirectToRoute('ui_import_index');
            }

            ($this->createImportJobHandler)(new CreateImportJobCommand(
                $user->getId()->toRfc4122(),
                $uploadedFile->getPathname(),
                $uploadedFile->getClientOriginalName(),
            ));

            $this->addFlash('success', 'File uploaded. Import job queued.');

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

        return isset($decoded['creationPayload']) && is_array($decoded['creationPayload']);
    }
}
