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
    // OCR.Space free tier hard limit.
    private const MAX_UPLOAD_SIZE = '1024K';
    private const int RATE_LIMITER_KEY_MAX_LENGTH = 200;

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

        $violations = $this->validator->validate($uploadedFile, [
            new Assert\File(
                maxSize: self::MAX_UPLOAD_SIZE,
                mimeTypes: self::ALLOWED_MIME_TYPES,
                maxSizeMessage: 'File is too large. Current import limit is 1 MB.',
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
}
