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

namespace App\PublicFuelStation\Application\Import;

use App\PublicFuelStation\Application\Repository\PublicFuelStationRepository;
use Throwable;

final readonly class PublicFuelStationImporter
{
    public function __construct(
        private PublicFuelStationCsvParser $parser,
        private PublicFuelStationRepository $repository,
    ) {
    }

    public function importFile(string $path, ?int $limit = null): PublicFuelStationImportResult
    {
        $processed = 0;
        $upserted = 0;
        $rejected = 0;

        try {
            foreach ($this->parser->parseFile($path) as $station) {
                if (null !== $limit && $processed >= $limit) {
                    break;
                }

                ++$processed;
                if (null === $station->latitudeMicroDegrees || null === $station->longitudeMicroDegrees) {
                    ++$rejected;

                    continue;
                }

                $this->repository->upsert($station);
                ++$upserted;
            }
        } catch (Throwable $e) {
            throw new PublicFuelStationImportException($processed, $upserted, $rejected, $e->getMessage(), $e);
        }

        return new PublicFuelStationImportResult($processed, $upserted, $rejected);
    }
}
