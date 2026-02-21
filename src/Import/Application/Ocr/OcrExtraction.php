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

final readonly class OcrExtraction
{
    /**
     * @param list<string>         $pages
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $provider,
        public string $text,
        public array $pages,
        public array $raw,
    ) {
    }
}
