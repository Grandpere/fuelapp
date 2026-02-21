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
use ApiPlatform\State\ProviderInterface;
use App\Security\Voter\StationVoter;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use App\Station\UI\Api\Resource\Output\StationOutput;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<StationOutput>
 */
final readonly class StationStateProvider implements ProviderInterface
{
    public function __construct(
        private StationRepository $repository,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $id = $uriVariables['id'] ?? null;
        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                return null;
            }

            if (!$this->authorizationChecker->isGranted(StationVoter::VIEW, $id)) {
                return null;
            }

            $station = $this->repository->get($id);

            return $station ? $this->toOutput($station) : null;
        }

        $resources = [];
        foreach ($this->repository->all() as $station) {
            $resources[] = $this->toOutput($station);
        }

        return $resources;
    }

    private function toOutput(Station $station): StationOutput
    {
        return new StationOutput(
            $station->id()->toString(),
            $station->name(),
            $station->streetName(),
            $station->postalCode(),
            $station->city(),
            $station->latitudeMicroDegrees(),
            $station->longitudeMicroDegrees(),
            $station->geocodingStatus(),
            $station->geocodingRequestedAt(),
            $station->geocodedAt(),
            $station->geocodingFailedAt(),
            $station->geocodingLastError(),
        );
    }
}
