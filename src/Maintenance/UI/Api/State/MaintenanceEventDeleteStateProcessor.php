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

namespace App\Maintenance\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Domain\MaintenanceEvent;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class MaintenanceEventDeleteStateProcessor implements ProcessorInterface
{
    public function __construct(
        private MaintenanceEventRepository $repository,
        private AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $ownerId = $this->currentUserId();
        $id = $uriVariables['id'] ?? null;
        if (null === $ownerId || !is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $event = $this->repository->get($id);
        if (!$event instanceof MaintenanceEvent || $event->ownerId() !== $ownerId) {
            throw new NotFoundHttpException();
        }

        $this->repository->delete($id);
    }

    private function currentUserId(): ?string
    {
        return $this->authenticatedUserIdProvider->getAuthenticatedUserId();
    }
}
