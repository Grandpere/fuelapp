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
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class OcrSpaceOcrProvider implements OcrProvider
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUri,
        private string $apiKey,
        private string $language,
        private int $engine,
    ) {
    }

    public function extract(string $filePath, string $mimeType): OcrExtraction
    {
        if ('' === trim($this->apiKey)) {
            throw OcrProviderException::permanent('OCR provider API key is missing.');
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
                throw OcrProviderException::retryable(sprintf('OCR.Space transient error with status code %d.', $statusCode));
            }

            if ($statusCode >= 400) {
                throw OcrProviderException::permanent(sprintf('OCR.Space client error with status code %d.', $statusCode));
            }

            try {
                /** @var array<string, mixed> $payload */
                $payload = $response->toArray(false);
            } catch (Throwable $throwable) {
                throw OcrProviderException::retryable(sprintf('OCR.Space malformed response: %s', $throwable->getMessage()), $throwable);
            }
        } catch (TransportExceptionInterface $transportException) {
            throw OcrProviderException::retryable(sprintf('OCR.Space transport failure: %s', $transportException->getMessage()), $transportException);
        } finally {
            fclose($fileHandle);
        }

        $isErrored = true === ($payload['IsErroredOnProcessing'] ?? false);
        if ($isErrored) {
            $message = $this->normalizeProviderErrorMessage($payload['ErrorMessage'] ?? null);

            throw OcrProviderException::permanent(sprintf('OCR.Space processing error: %s', $message));
        }

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
}
