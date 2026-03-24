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
use GdImage;
use Imagick;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class OcrSpaceOcrProvider implements OcrProvider
{
    private const CIRCUIT_OPEN_UNTIL_CACHE_KEY = 'import.ocr_space.circuit_open_until';
    private const CIRCUIT_FAILURE_COUNT_CACHE_KEY = 'import.ocr_space.circuit_failure_count';
    private const OCR_SPACE_MAX_FILE_SIZE_BYTES = 1_000_000;
    private const OCR_SPACE_TARGET_FILE_SIZE_BYTES = 995_000;
    private const OCR_SPACE_SIZE_LIMIT_MESSAGE = 'OCR provider limit exceeded after local optimization (max 1 MB).';
    private const IMAGICK_READ_DPI = 200;
    private const GD_ESTIMATED_BYTES_PER_PIXEL = 8;
    private const GD_PROCESSING_OVERHEAD_BYTES = 8_388_608;
    private const PHP_MEMORY_SAFETY_MARGIN_BYTES = 16_777_216;

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
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function extract(string $filePath, string $mimeType): OcrExtraction
    {
        if ('' === trim($this->apiKey)) {
            throw OcrProviderException::permanent('OCR provider API key is missing.');
        }

        if ($this->isCircuitOpen()) {
            $this->logger?->warning('import.ocr_space.circuit_open_fast_fail', [
                'provider' => 'ocr_space',
                'open_seconds' => max(5, $this->circuitBreakerOpenSeconds),
            ]);

            throw OcrProviderException::retryable('OCR.Space circuit breaker is open. Provider temporarily paused.');
        }

        [$preparedFilePath, $deletePreparedFile] = $this->prepareFileForUpload($filePath, $mimeType);
        $preparedFileSize = filesize($preparedFilePath);
        if (!is_int($preparedFileSize) || $preparedFileSize > self::OCR_SPACE_MAX_FILE_SIZE_BYTES) {
            if ($deletePreparedFile && is_file($preparedFilePath)) {
                unlink($preparedFilePath);
            }

            throw OcrProviderException::permanent(self::OCR_SPACE_SIZE_LIMIT_MESSAGE);
        }

        $fileHandle = fopen($preparedFilePath, 'rb');
        if (false === $fileHandle) {
            throw OcrProviderException::permanent('Unable to open stored import file for OCR.');
        }

        $fileType = $this->resolveOcrSpaceFileType($preparedFilePath, $mimeType);
        $requestBody = [
            'apikey' => $this->apiKey,
            'language' => $this->language,
            'OCREngine' => (string) $this->engine,
            'isTable' => 'true',
            'scale' => 'true',
            'isOverlayRequired' => 'false',
            'file' => $fileHandle,
        ];
        if (null !== $fileType) {
            $requestBody['filetype'] = $fileType;
        }

        try {
            $response = $this->httpClient->request('POST', sprintf('%s/image', rtrim($this->baseUri, '/')), [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => $requestBody,
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
            if ($deletePreparedFile && is_file($preparedFilePath)) {
                unlink($preparedFilePath);
            }
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
            $this->logger?->info('import.ocr_space.circuit_closed', [
                'provider' => 'ocr_space',
            ]);

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

        $this->logger?->warning('import.ocr_space.retryable_failure_recorded', [
            'provider' => 'ocr_space',
            'failure_count' => $failureCount,
            'failure_threshold' => $failureThreshold,
            'failure_window_seconds' => $failureWindowSeconds,
        ]);

        if ($failureCount < $failureThreshold) {
            return;
        }

        $openUntil = time() + $openSeconds;
        $openUntilItem = $this->cachePool->getItem(self::CIRCUIT_OPEN_UNTIL_CACHE_KEY);
        $openUntilItem->set($openUntil);
        $openUntilItem->expiresAfter($openSeconds + 30);
        $this->cachePool->save($openUntilItem);

        $this->cachePool->deleteItem(self::CIRCUIT_FAILURE_COUNT_CACHE_KEY);
        $this->logger?->warning('import.ocr_space.circuit_opened', [
            'provider' => 'ocr_space',
            'failure_threshold' => $failureThreshold,
            'failure_window_seconds' => $failureWindowSeconds,
            'open_seconds' => $openSeconds,
            'open_until_epoch' => $openUntil,
        ]);
    }

    private function resetRetryableFailureState(): void
    {
        $failureCountItem = $this->cachePool->getItem(self::CIRCUIT_FAILURE_COUNT_CACHE_KEY);
        $hadFailureState = $failureCountItem->isHit() && is_int($failureCountItem->get()) && (int) $failureCountItem->get() > 0;
        $this->cachePool->deleteItem(self::CIRCUIT_FAILURE_COUNT_CACHE_KEY);

        if ($hadFailureState) {
            $this->logger?->info('import.ocr_space.circuit_failure_state_reset', [
                'provider' => 'ocr_space',
            ]);
        }
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function prepareFileForUpload(string $filePath, string $mimeType): array
    {
        $fileSize = filesize($filePath);
        if (!is_int($fileSize) || $fileSize <= self::OCR_SPACE_MAX_FILE_SIZE_BYTES) {
            return [$filePath, false];
        }

        if (in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            [$imagickPreparedPath, $imagickDeletePreparedPath] = $this->prepareFileForUploadWithImagick($filePath, $mimeType);
            if ($imagickDeletePreparedPath) {
                return [$imagickPreparedPath, true];
            }
        }

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return [$filePath, false];
        }

        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled') || !function_exists('imagejpeg')) {
            return [$filePath, false];
        }

        $imageSize = @getimagesize($filePath);
        if (!is_array($imageSize)) {
            return [$filePath, false];
        }

        $sourceWidth = (int) $imageSize[0];
        $sourceHeight = (int) $imageSize[1];
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return [$filePath, false];
        }

        if (!$this->canAllocateGdPixels($sourceWidth * $sourceHeight)) {
            return [$filePath, false];
        }

        $sourceImage = $this->createImageResource($filePath, $mimeType);
        if (!$sourceImage instanceof GdImage) {
            return [$filePath, false];
        }

        $scaleFactors = [1.0, 0.9, 0.8, 0.7, 0.6, 0.5, 0.4, 0.3, 0.2];
        $qualityLevels = [82, 74, 66, 58, 50, 42, 34, 28, 22];
        $bestCandidatePath = null;
        $bestCandidateSize = PHP_INT_MAX;

        foreach ($scaleFactors as $scaleFactor) {
            $targetWidth = max(1, (int) floor($sourceWidth * $scaleFactor));
            $targetHeight = max(1, (int) floor($sourceHeight * $scaleFactor));

            if (!$this->canAllocateGdPixels(($sourceWidth * $sourceHeight) + ($targetWidth * $targetHeight))) {
                continue;
            }

            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$targetImage instanceof GdImage) {
                continue;
            }

            $backgroundColor = imagecolorallocate($targetImage, 255, 255, 255);
            if (false === $backgroundColor) {
                imagedestroy($targetImage);

                continue;
            }

            imagefill($targetImage, 0, 0, $backgroundColor);
            imagecopyresampled(
                $targetImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight,
            );

            foreach ($qualityLevels as $quality) {
                $tempPath = tempnam(sys_get_temp_dir(), 'fuelapp-ocr-');
                if (false === $tempPath) {
                    continue;
                }

                $encoded = imagejpeg($targetImage, $tempPath, $quality);
                if (false === $encoded) {
                    unlink($tempPath);

                    continue;
                }

                $optimizedSize = filesize($tempPath);
                if (is_int($optimizedSize) && $optimizedSize <= self::OCR_SPACE_TARGET_FILE_SIZE_BYTES) {
                    if (is_string($bestCandidatePath) && is_file($bestCandidatePath)) {
                        unlink($bestCandidatePath);
                    }

                    imagedestroy($targetImage);
                    imagedestroy($sourceImage);

                    return [$tempPath, true];
                }

                if (is_int($optimizedSize) && $optimizedSize < $bestCandidateSize) {
                    if (is_string($bestCandidatePath) && is_file($bestCandidatePath)) {
                        unlink($bestCandidatePath);
                    }
                    $bestCandidatePath = $tempPath;
                    $bestCandidateSize = $optimizedSize;

                    continue;
                }

                unlink($tempPath);
            }

            imagedestroy($targetImage);
        }

        imagedestroy($sourceImage);

        if (is_string($bestCandidatePath) && is_file($bestCandidatePath)) {
            if ($bestCandidateSize <= self::OCR_SPACE_MAX_FILE_SIZE_BYTES) {
                return [$bestCandidatePath, true];
            }

            unlink($bestCandidatePath);
        }

        return [$filePath, false];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function prepareFileForUploadWithImagick(string $filePath, string $mimeType): array
    {
        if (!class_exists(Imagick::class)) {
            return [$filePath, false];
        }

        $scaleFactors = [1.0, 0.9, 0.8, 0.7, 0.6, 0.5, 0.4, 0.3, 0.2];
        $qualityLevels = [82, 74, 66, 58, 50, 42, 34, 28, 22];
        $bestCandidatePath = null;
        $bestCandidateSize = PHP_INT_MAX;

        foreach ($scaleFactors as $scaleFactor) {
            foreach ($qualityLevels as $quality) {
                $tempPath = $this->renderImagickCandidate($filePath, $mimeType, $scaleFactor, $quality);
                if (null === $tempPath) {
                    continue;
                }

                $candidateSize = filesize($tempPath);
                if (is_int($candidateSize) && $candidateSize <= self::OCR_SPACE_TARGET_FILE_SIZE_BYTES) {
                    if (is_string($bestCandidatePath) && is_file($bestCandidatePath)) {
                        unlink($bestCandidatePath);
                    }

                    return [$tempPath, true];
                }

                if (is_int($candidateSize) && $candidateSize < $bestCandidateSize) {
                    if (is_string($bestCandidatePath) && is_file($bestCandidatePath)) {
                        unlink($bestCandidatePath);
                    }
                    $bestCandidatePath = $tempPath;
                    $bestCandidateSize = $candidateSize;

                    continue;
                }

                unlink($tempPath);
            }
        }

        if (is_string($bestCandidatePath) && is_file($bestCandidatePath)) {
            if ($bestCandidateSize <= self::OCR_SPACE_MAX_FILE_SIZE_BYTES) {
                return [$bestCandidatePath, true];
            }

            unlink($bestCandidatePath);
        }

        return [$filePath, false];
    }

    private function renderImagickCandidate(string $filePath, string $mimeType, float $scaleFactor, int $quality): ?string
    {
        try {
            $imagick = new Imagick();
            if ('application/pdf' === $mimeType) {
                $imagick->setResolution(self::IMAGICK_READ_DPI, self::IMAGICK_READ_DPI);
                $imagick->readImage($filePath.'[0]');
            } else {
                $imagick->readImage($filePath);
            }

            $imagick->setIteratorIndex(0);
            $imagick->stripImage();

            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            if ($width <= 0 || $height <= 0) {
                $imagick->clear();
                $imagick->destroy();

                return null;
            }

            $targetWidth = max(1, (int) floor($width * $scaleFactor));
            $targetHeight = max(1, (int) floor($height * $scaleFactor));
            if ($targetWidth !== $width || $targetHeight !== $height) {
                $imagick->resizeImage($targetWidth, $targetHeight, Imagick::FILTER_LANCZOS, 1.0, true);
            }

            $imagick->setImageColorspace(Imagick::COLORSPACE_GRAY);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality($quality);

            $tempPath = tempnam(sys_get_temp_dir(), 'fuelapp-ocr-imagick-');
            if (false === $tempPath) {
                $imagick->clear();
                $imagick->destroy();

                return null;
            }

            $written = $imagick->writeImage($tempPath);
            $imagick->clear();
            $imagick->destroy();

            if (false === $written) {
                unlink($tempPath);

                return null;
            }

            return $tempPath;
        } catch (Throwable) {
            return null;
        }
    }

    private function createImageResource(string $filePath, string $mimeType): ?GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($filePath) : null,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($filePath) : null,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : null,
            default => null,
        };

        return $image instanceof GdImage ? $image : null;
    }

    private function canAllocateGdPixels(int $pixels): bool
    {
        if ($pixels <= 0) {
            return false;
        }

        $memoryLimitBytes = $this->memoryLimitBytes();
        if (null === $memoryLimitBytes) {
            return true;
        }

        $availableBytes = $memoryLimitBytes - memory_get_usage(true) - self::PHP_MEMORY_SAFETY_MARGIN_BYTES;
        if ($availableBytes <= 0) {
            return false;
        }

        $requiredBytes = ($pixels * self::GD_ESTIMATED_BYTES_PER_PIXEL) + self::GD_PROCESSING_OVERHEAD_BYTES;

        return $requiredBytes <= $availableBytes;
    }

    private function memoryLimitBytes(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ('' === $raw || '-1' === $raw) {
            return null;
        }

        $lastChar = strtolower(substr($raw, -1));
        $value = (int) $raw;
        if ($value <= 0) {
            return null;
        }

        return match ($lastChar) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function resolveOcrSpaceFileType(string $preparedFilePath, string $originalMimeType): ?string
    {
        $detectedMime = @mime_content_type($preparedFilePath);
        $normalizedMime = is_string($detectedMime) && '' !== trim($detectedMime)
            ? strtolower(trim($detectedMime))
            : strtolower(trim($originalMimeType));

        return match ($normalizedMime) {
            'application/pdf' => 'PDF',
            'image/jpeg' => 'JPG',
            'image/png' => 'PNG',
            'image/webp' => 'WEBP',
            default => null,
        };
    }
}
