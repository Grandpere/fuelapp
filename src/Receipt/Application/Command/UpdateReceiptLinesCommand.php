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

namespace App\Receipt\Application\Command;

final readonly class UpdateReceiptLinesCommand
{
    /**
     * @param list<CreateReceiptLineCommand> $lines
     */
    public function __construct(
        public string $receiptId,
        public array $lines,
        public bool $allowSystemScope = false,
    ) {
    }
}
