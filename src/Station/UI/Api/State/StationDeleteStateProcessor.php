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

namespace App\Station\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Station\Application\Repository\StationRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class StationDeleteStateProcessor implements ProcessorInterface
{
    public function __construct(private StationRepository $repository)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || '' === $id || !Uuid::isValid($id)) {
            return;
        }

        try {
            $this->repository->delete($id);
        } catch (ForeignKeyConstraintViolationException) {
            throw new ConflictHttpException('Station is linked to receipts and cannot be deleted.');
        }
    }
}
