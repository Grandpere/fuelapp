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
use App\Import\Domain\ImportJob;

final readonly class CreateImportJobHandler
{
    public function __construct(
        private ImportFileStorage $storage,
        private ImportJobRepository $repository,
    ) {
    }

    public function __invoke(CreateImportJobCommand $command): ImportJob
    {
        $storedFile = $this->storage->store($command->sourcePath, $command->originalFilename);

        $job = ImportJob::createQueued(
            $command->ownerId,
            $storedFile->storage,
            $storedFile->path,
            $storedFile->originalFilename,
            $storedFile->mimeType,
            $storedFile->sizeBytes,
            $storedFile->checksumSha256,
        );

        $this->repository->save($job);

        return $job;
    }
}
