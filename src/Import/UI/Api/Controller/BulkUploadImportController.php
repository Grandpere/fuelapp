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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class BulkUploadImportController extends AbstractController
{
    private const int RATE_LIMITER_KEY_MAX_LENGTH = 200;

    public function __construct(
        private readonly BulkImportUploadProcessor $bulkImportUploadProcessor,
        #[Autowire(service: 'limiter.api_import_bulk')]
        private readonly RateLimiterFactory $bulkUploadLimiter,
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

    private function consumeRateLimitOrBuildResponse(string $userId, ?string $clientIp): ?JsonResponse
    {
        $limiter = $this->bulkUploadLimiter->create($this->buildRateLimiterKey($userId, $clientIp));
        $limit = $limiter->consume(1);
        if ($limit->isAccepted()) {
            return null;
        }

        $retryAfter = $limit->getRetryAfter();
        $retryAfterSeconds = max(1, $retryAfter->getTimestamp() - time());

        $response = $this->json([
            'message' => 'Too many bulk upload attempts. Please try again later.',
        ], Response::HTTP_TOO_MANY_REQUESTS);
        $response->headers->set('Retry-After', (string) $retryAfterSeconds);

        return $response;
    }

    private function buildRateLimiterKey(string $userId, ?string $clientIp): string
    {
        $ip = is_string($clientIp) && '' !== trim($clientIp) ? trim($clientIp) : 'unknown-ip';
        $rawKey = sprintf('api-import-bulk:%s|%s', $userId, $ip);

        return mb_substr($rawKey, 0, self::RATE_LIMITER_KEY_MAX_LENGTH);
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
