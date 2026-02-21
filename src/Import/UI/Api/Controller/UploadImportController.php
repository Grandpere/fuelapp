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

namespace App\Import\UI\Api\Controller;

use App\Import\Application\Command\CreateImportJobCommand;
use App\Import\Application\Command\CreateImportJobHandler;
use App\Security\AuthenticatedUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UploadImportController extends AbstractController
{
    private const MAX_UPLOAD_SIZE = '10M';

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly CreateImportJobHandler $handler,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/imports', name: 'api_import_upload', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AuthenticatedUser) {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile) {
            return $this->json([
                'message' => 'Validation failed.',
                'errors' => [['field' => 'file', 'message' => 'File is required.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $violations = $this->validator->validate($uploadedFile, [
            new Assert\File(
                maxSize: self::MAX_UPLOAD_SIZE,
                mimeTypes: self::ALLOWED_MIME_TYPES,
                mimeTypesMessage: 'Unsupported file type. Allowed: PDF, JPEG, PNG, WEBP.',
            ),
        ]);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = ['field' => 'file', 'message' => $violation->getMessage()];
            }

            return $this->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $job = ($this->handler)(new CreateImportJobCommand(
            $user->getId()->toRfc4122(),
            $uploadedFile->getPathname(),
            $uploadedFile->getClientOriginalName(),
        ));

        return $this->json([
            'id' => $job->id()->toString(),
            'status' => $job->status()->value,
            'createdAt' => $job->createdAt()->format(DATE_ATOM),
        ], Response::HTTP_CREATED);
    }
}
