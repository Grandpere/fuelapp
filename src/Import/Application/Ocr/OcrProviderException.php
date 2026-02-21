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

namespace App\Import\Application\Ocr;

use RuntimeException;
use Throwable;

final class OcrProviderException extends RuntimeException
{
    private function __construct(string $message, private readonly bool $retryable, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function retryable(string $message, ?Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    public static function permanent(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
