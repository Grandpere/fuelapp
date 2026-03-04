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

use App\Import\UI\Upload\BulkImportUploadProcessor;
use App\Security\AuthenticatedUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BulkUploadImportController extends AbstractController
{
    public function __construct(private readonly BulkImportUploadProcessor $bulkImportUploadProcessor)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AuthenticatedUser) {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $uploadedFiles = $this->readUploadedFiles($request);
        if ([] === $uploadedFiles) {
            return $this->json([
                'message' => 'Validation failed.',
                'errors' => [['field' => 'files', 'message' => 'At least one file is required.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->bulkImportUploadProcessor->process($user->getId()->toRfc4122(), $uploadedFiles);
        $statusCode = $result->acceptedCount() > 0 ? Response::HTTP_CREATED : Response::HTTP_UNPROCESSABLE_ENTITY;

        return $this->json([
            'acceptedCount' => $result->acceptedCount(),
            'rejectedCount' => $result->rejectedCount(),
            'accepted' => $result->accepted(),
            'rejected' => $result->rejected(),
        ], $statusCode);
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
}
