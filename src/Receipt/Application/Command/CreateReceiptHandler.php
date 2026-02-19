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

use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Receipt;
use App\Receipt\Domain\ReceiptLine;

final readonly class CreateReceiptHandler
{
    public function __construct(private ReceiptRepository $repository)
    {
    }

    public function __invoke(CreateReceiptCommand $command): Receipt
    {
        $lines = [];
        foreach ($command->lines as $line) {
            $lines[] = ReceiptLine::create(
                $line->fuelType,
                $line->quantityMilliLiters,
                $line->unitPriceDeciCentsPerLiter,
                $line->vatRatePercent,
            );
        }

        $receipt = Receipt::create(
            $command->issuedAt,
            $lines,
            $command->stationId,
        );

        $this->repository->save($receipt);

        return $receipt;
    }
}
