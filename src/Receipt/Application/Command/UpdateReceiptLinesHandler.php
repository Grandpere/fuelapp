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
use App\Receipt\Domain\ValueObject\ReceiptId;

final readonly class UpdateReceiptLinesHandler
{
    public function __construct(private ReceiptRepository $repository)
    {
    }

    public function __invoke(UpdateReceiptLinesCommand $command): ?Receipt
    {
        $receipt = $this->repository->get($command->receiptId);
        $ownerIdForSave = null;

        if (null === $receipt && $command->allowSystemScope) {
            $receipt = $this->repository->getForSystem($command->receiptId);
            if (null !== $receipt) {
                $ownerIdForSave = $this->repository->ownerIdForSystem($command->receiptId);
            }
        }

        if (null === $receipt) {
            return null;
        }

        $lines = [];
        foreach ($command->lines as $line) {
            $lines[] = ReceiptLine::create(
                $line->fuelType,
                $line->quantityMilliLiters,
                $line->unitPriceDeciCentsPerLiter,
                $line->vatRatePercent,
            );
        }

        $updated = Receipt::reconstitute(
            ReceiptId::fromString($receipt->id()->toString()),
            $receipt->issuedAt(),
            $lines,
            $receipt->stationId(),
            $receipt->vehicleId(),
        );

        if (null !== $ownerIdForSave) {
            $this->repository->saveForOwner($updated, $ownerIdForSave);
        } else {
            $this->repository->save($updated);
        }

        return $updated;
    }
}
