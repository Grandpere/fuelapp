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

namespace App\Import\Infrastructure\Ocr;

use App\Import\Application\Ocr\OcrExtraction;
use App\Import\Application\Ocr\OcrProvider;
use App\Import\Application\Ocr\OcrProviderException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class OcrSpaceOcrProvider implements OcrProvider
{
    private const CIRCUIT_OPEN_UNTIL_CACHE_KEY = 'import.ocr_space.circuit_open_until';
    private const CIRCUIT_FAILURE_COUNT_CACHE_KEY = 'import.ocr_space.circuit_failure_count';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cachePool,
        private string $baseUri,
        private string $apiKey,
        private string $language,
        private int $engine,
        private int $circuitBreakerFailureThreshold = 3,
        private int $circuitBreakerFailureWindowSeconds = 60,
        private int $circuitBreakerOpenSeconds = 180,
    ) {
    }

    public function extract(string $filePath, string $mimeType): OcrExtraction
    {
        if ('' === trim($this->apiKey)) {
            throw OcrProviderException::permanent('OCR provider API key is missing.');
        }

        if ($this->isCircuitOpen()) {
            throw OcrProviderException::retryable('OCR.Space circuit breaker is open. Provider temporarily paused.');
        }

        $fileHandle = fopen($filePath, 'rb');
        if (false === $fileHandle) {
            throw OcrProviderException::permanent('Unable to open stored import file for OCR.');
        }

        try {
            $response = $this->httpClient->request('POST', sprintf('%s/image', rtrim($this->baseUri, '/')), [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => [
                    'apikey' => $this->apiKey,
                    'language' => $this->language,
                    'OCREngine' => (string) $this->engine,
                    'isOverlayRequired' => 'false',
                    'file' => $fileHandle,
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            if (429 === $statusCode || $statusCode >= 500) {
                $this->recordRetryableFailure();
                throw OcrProviderException::retryable(sprintf('OCR.Space transient error with status code %d.', $statusCode));
            }

            if ($statusCode >= 400) {
                throw OcrProviderException::permanent(sprintf('OCR.Space client error with status code %d.', $statusCode));
            }

            try {
                /** @var array<string, mixed> $payload */
                $payload = $response->toArray(false);
            } catch (Throwable $throwable) {
                $this->recordRetryableFailure();
                throw OcrProviderException::retryable(sprintf('OCR.Space malformed response: %s', $throwable->getMessage()), $throwable);
            }
        } catch (TransportExceptionInterface $transportException) {
            $this->recordRetryableFailure();
            throw OcrProviderException::retryable(sprintf('OCR.Space transport failure: %s', $transportException->getMessage()), $transportException);
        } finally {
            fclose($fileHandle);
        }

        $isErrored = true === ($payload['IsErroredOnProcessing'] ?? false);
        if ($isErrored) {
            $message = $this->normalizeProviderErrorMessage($payload['ErrorMessage'] ?? null);

            if ($this->isRetryableProviderError($message)) {
                $this->recordRetryableFailure();
                throw OcrProviderException::retryable(sprintf('OCR.Space processing error: %s', $message));
            }

            throw OcrProviderException::permanent(sprintf('OCR.Space processing error: %s', $message));
        }

        $this->resetRetryableFailureState();

        $pages = [];
        $parsedResults = $payload['ParsedResults'] ?? null;
        if (is_array($parsedResults)) {
            foreach ($parsedResults as $result) {
                if (!is_array($result)) {
                    continue;
                }

                $text = $result['ParsedText'] ?? null;
                if (!is_string($text)) {
                    continue;
                }

                $normalized = trim($text);
                if ('' !== $normalized) {
                    $pages[] = $normalized;
                }
            }
        }

        return new OcrExtraction(
            'ocr_space',
            implode("\n\n", $pages),
            $pages,
            $payload,
        );
    }

    private function normalizeProviderErrorMessage(mixed $error): string
    {
        if (is_string($error) && '' !== trim($error)) {
            return trim($error);
        }

        if (is_array($error)) {
            $messages = [];
            foreach ($error as $item) {
                if (is_string($item) && '' !== trim($item)) {
                    $messages[] = trim($item);
                }
            }

            if ([] !== $messages) {
                return implode(' | ', $messages);
            }
        }

        return 'unknown provider error';
    }

    private function isRetryableProviderError(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        if ('' === $normalized) {
            return false;
        }

        $retryableHints = [
            'system resource exhaustion',
            'ocr binary failed',
            'timeout',
            'temporarily unavailable',
            'try again later',
            'server busy',
            'rate limit',
            'too many requests',
        ];

        foreach ($retryableHints as $hint) {
            if (str_contains($normalized, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function isCircuitOpen(): bool
    {
        $openUntilItem = $this->cachePool->getItem(self::CIRCUIT_OPEN_UNTIL_CACHE_KEY);
        if (!$openUntilItem->isHit()) {
            return false;
        }

        $openUntil = $openUntilItem->get();
        if (!is_int($openUntil)) {
            $this->cachePool->deleteItem(self::CIRCUIT_OPEN_UNTIL_CACHE_KEY);

            return false;
        }

        if ($openUntil <= time()) {
            $this->cachePool->deleteItem(self::CIRCUIT_OPEN_UNTIL_CACHE_KEY);

            return false;
        }

        return true;
    }

    private function recordRetryableFailure(): void
    {
        $failureThreshold = max(1, $this->circuitBreakerFailureThreshold);
        $failureWindowSeconds = max(5, $this->circuitBreakerFailureWindowSeconds);
        $openSeconds = max(5, $this->circuitBreakerOpenSeconds);

        $failureCountItem = $this->cachePool->getItem(self::CIRCUIT_FAILURE_COUNT_CACHE_KEY);
        $failureCount = 0;
        if ($failureCountItem->isHit() && is_int($failureCountItem->get())) {
            $failureCount = (int) $failureCountItem->get();
        }

        ++$failureCount;
        $failureCountItem->set($failureCount);
        $failureCountItem->expiresAfter($failureWindowSeconds);
        $this->cachePool->save($failureCountItem);

        if ($failureCount < $failureThreshold) {
            return;
        }

        $openUntil = time() + $openSeconds;
        $openUntilItem = $this->cachePool->getItem(self::CIRCUIT_OPEN_UNTIL_CACHE_KEY);
        $openUntilItem->set($openUntil);
        $openUntilItem->expiresAfter($openSeconds + 30);
        $this->cachePool->save($openUntilItem);

        $this->cachePool->deleteItem(self::CIRCUIT_FAILURE_COUNT_CACHE_KEY);
    }

    private function resetRetryableFailureState(): void
    {
        $this->cachePool->deleteItem(self::CIRCUIT_FAILURE_COUNT_CACHE_KEY);
    }
}
