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
use ReflectionMethod;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
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

        $provider = new OcrSpaceOcrProvider($httpClient, new ArrayAdapter(), 'https://api.ocr.space/parse', 'test-key', 'eng', 2);

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

        $provider = new OcrSpaceOcrProvider($httpClient, new ArrayAdapter(), 'https://api.ocr.space/parse', 'test-key', 'eng', 2);

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

        $provider = new OcrSpaceOcrProvider($httpClient, new ArrayAdapter(), 'https://api.ocr.space/parse', 'test-key', 'eng', 2);

        $this->expectException(OcrProviderException::class);

        try {
            $provider->extract($this->createTempFile('sample'), 'application/pdf');
        } catch (OcrProviderException $e) {
            self::assertFalse($e->isRetryable());

            throw $e;
        }
    }

    public function testItThrowsRetryableExceptionOnProviderCapacityErrorPayload(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"IsErroredOnProcessing":true,"ErrorMessage":["Error E500: System Resource Exhaustion (OCR Binary Failed)"]}', ['http_code' => 200]),
        ], 'https://api.ocr.space/parse');

        $provider = new OcrSpaceOcrProvider($httpClient, new ArrayAdapter(), 'https://api.ocr.space/parse', 'test-key', 'eng', 2);

        $this->expectException(OcrProviderException::class);

        try {
            $provider->extract($this->createTempFile('sample'), 'application/pdf');
        } catch (OcrProviderException $e) {
            self::assertTrue($e->isRetryable());

            throw $e;
        }
    }

    public function testItThrowsRetryableExceptionOnTransportFailure(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('network down');
        }, 'https://api.ocr.space/parse');

        $provider = new OcrSpaceOcrProvider($httpClient, new ArrayAdapter(), 'https://api.ocr.space/parse', 'test-key', 'eng', 2);

        $this->expectException(OcrProviderException::class);

        try {
            $provider->extract($this->createTempFile('sample'), 'application/pdf');
        } catch (OcrProviderException $e) {
            self::assertTrue($e->isRetryable());

            throw $e;
        }
    }

    public function testItOpensCircuitBreakerAfterConsecutiveTransientFailures(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('{"error":"busy"}', ['http_code' => 503]);
        }, 'https://api.ocr.space/parse');

        $provider = new OcrSpaceOcrProvider(
            $httpClient,
            new ArrayAdapter(),
            'https://api.ocr.space/parse',
            'test-key',
            'eng',
            2,
            2,
            60,
            300,
        );
        $firstFailure = $this->captureProviderException($provider);
        self::assertTrue($firstFailure->isRetryable());

        $secondFailure = $this->captureProviderException($provider);
        self::assertTrue($secondFailure->isRetryable());

        $openCircuitFailure = $this->captureProviderException($provider);
        self::assertTrue($openCircuitFailure->isRetryable());
        self::assertStringContainsString('circuit breaker is open', $openCircuitFailure->getMessage());

        self::assertSame(2, $calls);
    }

    public function testItCompressesOversizedImageBeforeUpload(): void
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            self::markTestSkipped('GD extension is required for image compression test.');
        }

        $oversizedImagePath = $this->createOversizedJpegFile();
        $provider = new OcrSpaceOcrProvider(new MockHttpClient(), new ArrayAdapter(), 'https://api.ocr.space/parse', 'test-key', 'eng', 2);
        $prepareMethod = new ReflectionMethod(OcrSpaceOcrProvider::class, 'prepareFileForUpload');
        $prepareMethod->setAccessible(true);

        /** @var array{0: string, 1: bool} $result */
        $result = $prepareMethod->invoke($provider, $oversizedImagePath, 'image/jpeg');
        [$preparedPath, $shouldDeletePreparedPath] = $result;
        $preparedFileSize = filesize($preparedPath);

        self::assertNotSame($oversizedImagePath, $preparedPath);
        self::assertTrue($shouldDeletePreparedPath);
        self::assertIsInt($preparedFileSize);
        self::assertLessThanOrEqual(950000, $preparedFileSize);

        unlink($oversizedImagePath);
        unlink($preparedPath);
    }

    public function testItFailsBeforeProviderCallWhenPreparedFileStillExceedsProviderLimit(): void
    {
        $providerCalls = 0;
        $httpClient = new MockHttpClient(static function () use (&$providerCalls): MockResponse {
            ++$providerCalls;

            return new MockResponse('{}');
        }, 'https://api.ocr.space/parse');

        $provider = new OcrSpaceOcrProvider($httpClient, new ArrayAdapter(), 'https://api.ocr.space/parse', 'test-key', 'eng', 2);
        $oversizedPath = $this->createTempFile(str_repeat('A', 1_200_000));

        $this->expectException(OcrProviderException::class);
        $this->expectExceptionMessage('OCR provider limit exceeded after local optimization');

        try {
            $provider->extract($oversizedPath, 'image/png');
        } catch (OcrProviderException $exception) {
            self::assertFalse($exception->isRetryable());
            self::assertSame(0, $providerCalls);

            throw $exception;
        }
    }

    private function captureProviderException(OcrSpaceOcrProvider $provider): OcrProviderException
    {
        try {
            $provider->extract($this->createTempFile('sample'), 'application/pdf');
        } catch (OcrProviderException $exception) {
            return $exception;
        }

        self::fail('Expected OcrProviderException was not thrown.');
    }

    private function createTempFile(string $content): string
    {
        $path = sys_get_temp_dir().'/fuelapp-ocr-space-'.uniqid('', true);
        file_put_contents($path, $content);

        return $path;
    }

    private function createOversizedJpegFile(): string
    {
        $image = imagecreatetruecolor(2100, 2100);
        self::assertNotFalse($image);

        for ($line = 0; $line < 2100; ++$line) {
            $color = imagecolorallocate($image, $line % 255, ($line * 3) % 255, ($line * 7) % 255);
            self::assertNotFalse($color);
            imageline($image, 0, $line, 2099, $line, $color);
        }

        $path = tempnam(sys_get_temp_dir(), 'fuelapp-ocr-big-');
        self::assertNotFalse($path);
        imagejpeg($image, $path, 100);
        imagedestroy($image);

        $size = filesize($path);
        self::assertIsInt($size);
        self::assertGreaterThan(1_000_000, $size);

        return $path;
    }
}
