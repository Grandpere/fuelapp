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

namespace App\Import\Application\Parsing;

use App\Import\Application\Ocr\OcrExtraction;

interface ReceiptOcrParser
{
    public function parse(OcrExtraction $extraction): ParsedReceiptDraft;
}
