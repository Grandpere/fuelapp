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

namespace App\Import\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\UI\Api\Resource\Output\ImportJobOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ImportJobOutput>
 */
final readonly class ImportJobStateProvider implements ProviderInterface
{
    public function __construct(
        private ImportJobRepository $repository,
        private ImportJobOutputMapper $outputMapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $id = $uriVariables['id'] ?? null;
        if (null !== $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $job = $this->repository->get($id);
            if (null === $job) {
                throw new NotFoundHttpException();
            }

            return $this->outputMapper->map($job);
        }

        $outputs = [];
        foreach ($this->repository->all() as $job) {
            $outputs[] = $this->outputMapper->map($job);
        }

        return $outputs;
    }
}
