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
use App\Receipt\Domain\ValueObject\ReceiptId;
use App\Station\Domain\ValueObject\StationId;
use App\Vehicle\Domain\ValueObject\VehicleId;

final readonly class UpdateReceiptMetadataHandler
{
    public function __construct(private ReceiptRepository $repository)
    {
    }

    public function __invoke(UpdateReceiptMetadataCommand $command): ?Receipt
    {
        $receipt = $this->repository->get($command->receiptId);
        if (null === $receipt) {
            return null;
        }

        $updated = Receipt::reconstitute(
            ReceiptId::fromString($receipt->id()->toString()),
            $command->issuedAt,
            $receipt->lines(),
            null === $command->stationId ? null : StationId::fromString($command->stationId),
            null === $command->vehicleId ? null : VehicleId::fromString($command->vehicleId),
            $command->odometerKilometers,
        );

        $this->repository->save($updated);

        return $updated;
    }
}
