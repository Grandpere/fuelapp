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

namespace App\Import\Application\Command;

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Application\Storage\ImportFileStorage;
use InvalidArgumentException;

final readonly class DeleteImportJobHandler
{
    public function __construct(
        private ImportJobRepository $repository,
        private ImportFileStorage $fileStorage,
    ) {
    }

    public function __invoke(DeleteImportJobCommand $command): void
    {
        $job = $this->repository->getForSystem($command->importJobId);
        if (null === $job) {
            throw new InvalidArgumentException('Import job not found.');
        }

        $this->fileStorage->delete($job->storage(), $job->filePath());
        $this->repository->deleteForSystem($job->id()->toString());
    }
}
