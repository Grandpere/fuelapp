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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UploadImportController extends AbstractController
{
    private const MAX_IMAGE_UPLOAD_SIZE = '8M';
    private const MAX_IMAGE_UPLOAD_BYTES = 8_388_608;
    private const MAX_PDF_UPLOAD_BYTES = 1_048_576;
    private const SIZE_LIMIT_MESSAGE = 'File is too large. Current import limits: 8 MB for images, 1 MB for PDF.';
    private const int RATE_LIMITER_KEY_MAX_LENGTH = 200;

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];
    /** @var array<string, list<string>> */
    private const EXTENSIONS_BY_MIME = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    public function __construct(
        private readonly CreateImportJobHandler $handler,
        private readonly ValidatorInterface $validator,
        #[Autowire(service: 'limiter.api_import_upload')]
        private readonly RateLimiterFactory $uploadLimiter,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AuthenticatedUser) {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $rateLimitedResponse = $this->consumeRateLimitOrBuildResponse($user->getId()->toRfc4122(), $request->getClientIp());
        if (null !== $rateLimitedResponse) {
            return $rateLimitedResponse;
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile) {
            return $this->json([
                'message' => 'Validation failed.',
                'errors' => [['field' => 'file', 'message' => 'File is required.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $uploadUnusableReason = $this->uploadUnusableReason($uploadedFile);
        if (null !== $uploadUnusableReason) {
            return $this->json([
                'message' => 'Validation failed.',
                'errors' => [['field' => 'file', 'message' => $uploadUnusableReason]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $violations = $this->validator->validate($uploadedFile, [
            new Assert\File(
                maxSize: self::MAX_IMAGE_UPLOAD_SIZE,
                mimeTypes: self::ALLOWED_MIME_TYPES,
                maxSizeMessage: self::SIZE_LIMIT_MESSAGE,
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

        $mimeExtensionError = $this->mimeExtensionMismatchReason(
            $uploadedFile->getPathname(),
            $uploadedFile->getClientOriginalName(),
        );
        if (null !== $mimeExtensionError) {
            return $this->json([
                'message' => 'Validation failed.',
                'errors' => [['field' => 'file', 'message' => $mimeExtensionError]],
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

    private function consumeRateLimitOrBuildResponse(string $userId, ?string $clientIp): ?JsonResponse
    {
        $limiter = $this->uploadLimiter->create($this->buildRateLimiterKey($userId, $clientIp));
        $limit = $limiter->consume(1);
        if ($limit->isAccepted()) {
            return null;
        }

        $retryAfter = $limit->getRetryAfter();
        $retryAfterSeconds = max(1, $retryAfter->getTimestamp() - time());

        $response = $this->json([
            'message' => 'Too many upload attempts. Please try again later.',
        ], Response::HTTP_TOO_MANY_REQUESTS);
        $response->headers->set('Retry-After', (string) $retryAfterSeconds);

        return $response;
    }

    private function buildRateLimiterKey(string $userId, ?string $clientIp): string
    {
        $ip = is_string($clientIp) && '' !== trim($clientIp) ? trim($clientIp) : 'unknown-ip';
        $rawKey = sprintf('api-import-upload:%s|%s', $userId, $ip);

        return mb_substr($rawKey, 0, self::RATE_LIMITER_KEY_MAX_LENGTH);
    }

    private function uploadUnusableReason(UploadedFile $uploadedFile): ?string
    {
        if (!$uploadedFile->isValid()) {
            if (\UPLOAD_ERR_OK === $uploadedFile->getError()) {
                return 'Uploaded file is invalid or temporary file is unavailable.';
            }

            return $this->uploadErrorMessage($uploadedFile->getError());
        }

        $pathname = $uploadedFile->getPathname();
        if ('' === $pathname) {
            return 'Uploaded file is invalid or temporary file is unavailable.';
        }

        if (!is_file($pathname) || !is_readable($pathname)) {
            return 'Uploaded file is invalid or temporary file is unavailable.';
        }

        return null;
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            \UPLOAD_ERR_INI_SIZE => sprintf(
                'Upload rejected by server: file exceeds PHP limit (%s).',
                (string) ini_get('upload_max_filesize'),
            ),
            \UPLOAD_ERR_FORM_SIZE => 'Upload rejected: file exceeds HTML form limit.',
            \UPLOAD_ERR_PARTIAL => 'Upload failed: file was only partially uploaded.',
            \UPLOAD_ERR_NO_FILE => 'Upload failed: no file was uploaded.',
            \UPLOAD_ERR_NO_TMP_DIR => 'Upload failed: missing temporary directory on server.',
            \UPLOAD_ERR_CANT_WRITE => 'Upload failed: unable to write file to disk.',
            \UPLOAD_ERR_EXTENSION => 'Upload blocked by a PHP extension.',
            default => 'Uploaded file is invalid or temporary file is unavailable.',
        };
    }

    private function mimeExtensionMismatchReason(string $sourcePath, string $originalFilename): ?string
    {
        $extension = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        if ('' === $extension) {
            return 'File extension is required (pdf, jpg, jpeg, png, webp).';
        }

        $detectedMime = @mime_content_type($sourcePath);
        if (!is_string($detectedMime) || '' === trim($detectedMime)) {
            return 'Unable to determine uploaded file type.';
        }

        $normalizedMime = strtolower(trim($detectedMime));
        $allowedExtensions = self::EXTENSIONS_BY_MIME[$normalizedMime] ?? null;
        if (null === $allowedExtensions) {
            return 'Unsupported file type. Allowed: PDF, JPEG, PNG, WEBP.';
        }

        $maxBytes = 'application/pdf' === $normalizedMime ? self::MAX_PDF_UPLOAD_BYTES : self::MAX_IMAGE_UPLOAD_BYTES;
        $fileSize = filesize($sourcePath);
        if (is_int($fileSize) && $fileSize > $maxBytes) {
            return self::SIZE_LIMIT_MESSAGE;
        }

        if (!in_array($extension, $allowedExtensions, true)) {
            return sprintf('File extension ".%s" does not match detected content type "%s".', $extension, $normalizedMime);
        }

        return null;
    }
}
