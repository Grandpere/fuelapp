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
use App\Security\Voter\StationVoter;
use App\Station\Application\Command\UpdateStationAddressCommand;
use App\Station\Application\Command\UpdateStationAddressHandler;
use App\Station\Domain\Station;
use App\Station\UI\Api\Resource\Input\UpdateStationAddressInput;
use App\Station\UI\Api\Resource\Output\StationOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<UpdateStationAddressInput, StationOutput>
 */
final readonly class StationUpdateStateProcessor implements ProcessorInterface
{
    public function __construct(
        private UpdateStationAddressHandler $handler,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || '' === $id || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        if (!$this->authorizationChecker->isGranted(StationVoter::EDIT, $id)) {
            throw new NotFoundHttpException();
        }

        $station = ($this->handler)(new UpdateStationAddressCommand(
            $id,
            $data->name,
            $data->streetName,
            $data->postalCode,
            $data->city,
        ));

        if (!$station instanceof Station) {
            throw new NotFoundHttpException();
        }

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
