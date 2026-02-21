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

use App\Import\Application\Message\ProcessImportJobMessage;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RetryImportJobHandler
{
    public function __construct(
        private ImportJobRepository $repository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(RetryImportJobCommand $command): ImportJob
    {
        $job = $this->repository->getForSystem($command->importJobId);
        if (null === $job) {
            throw new InvalidArgumentException('Import job not found.');
        }

        if (ImportJobStatus::FAILED !== $job->status()) {
            throw new InvalidArgumentException('Only failed jobs can be retried.');
        }

        $job->markQueuedForRetry();
        $this->repository->save($job);
        $this->messageBus->dispatch(new ProcessImportJobMessage($job->id()->toString()));

        return $job;
    }
}
