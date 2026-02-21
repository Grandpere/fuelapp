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

namespace App\Tests\Unit\Import\Infrastructure\Ocr;

use App\Import\Application\Ocr\OcrProviderException;
use App\Import\Infrastructure\Ocr\OcrSpaceOcrProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OcrSpaceOcrProviderTest extends TestCase
{
    public function testItExtractsAndNormalizesParsedText(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"IsErroredOnProcessing":false,"ParsedResults":[{"ParsedText":"TOTAL 80.00"},{"ParsedText":"VAT 13.33"}]}', ['http_code' => 200]),
        ], 'https://api.ocr.space/parse');

        $provider = new OcrSpaceOcrProvider($httpClient, 'https://api.ocr.space/parse', 'test-key', 'eng', 2);

        $file = $this->createTempFile('sample');
        $result = $provider->extract($file, 'image/png');

        self::assertSame('ocr_space', $result->provider);
        self::assertSame("TOTAL 80.00\n\nVAT 13.33", $result->text);
        self::assertCount(2, $result->pages);
    }

    public function testItThrowsRetryableExceptionOnTransientStatus(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":"busy"}', ['http_code' => 503]),
        ], 'https://api.ocr.space/parse');

        $provider = new OcrSpaceOcrProvider($httpClient, 'https://api.ocr.space/parse', 'test-key', 'eng', 2);

        $this->expectException(OcrProviderException::class);

        try {
            $provider->extract($this->createTempFile('sample'), 'application/pdf');
        } catch (OcrProviderException $e) {
            self::assertTrue($e->isRetryable());

            throw $e;
        }
    }

    public function testItThrowsPermanentExceptionOnProviderErrorPayload(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"IsErroredOnProcessing":true,"ErrorMessage":["File failed validation"]}', ['http_code' => 200]),
        ], 'https://api.ocr.space/parse');

        $provider = new OcrSpaceOcrProvider($httpClient, 'https://api.ocr.space/parse', 'test-key', 'eng', 2);

        $this->expectException(OcrProviderException::class);

        try {
            $provider->extract($this->createTempFile('sample'), 'application/pdf');
        } catch (OcrProviderException $e) {
            self::assertFalse($e->isRetryable());

            throw $e;
        }
    }

    public function testItThrowsRetryableExceptionOnTransportFailure(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('network down');
        }, 'https://api.ocr.space/parse');

        $provider = new OcrSpaceOcrProvider($httpClient, 'https://api.ocr.space/parse', 'test-key', 'eng', 2);

        $this->expectException(OcrProviderException::class);

        try {
            $provider->extract($this->createTempFile('sample'), 'application/pdf');
        } catch (OcrProviderException $e) {
            self::assertTrue($e->isRetryable());

            throw $e;
        }
    }

    private function createTempFile(string $content): string
    {
        $path = sys_get_temp_dir().'/fuelapp-ocr-space-'.uniqid('', true);
        file_put_contents($path, $content);

        return $path;
    }
}
