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

namespace App\Admin\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use App\Station\UI\Api\Resource\Input\UpdateStationAddressInput;
use App\Station\UI\Api\Resource\Output\StationOutput;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, StationOutput>
 */
final readonly class AdminStationStateProcessor implements ProcessorInterface
{
    public function __construct(private StationRepository $repository)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StationOutput
    {
        if (!$data instanceof UpdateStationAddressInput) {
            throw new InvalidArgumentException('Invalid station input.');
        }

        $id = $uriVariables['id'] ?? null;
        $station = null;

        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $station = $this->repository->getForSystem($id);
            if (!$station instanceof Station) {
                throw new NotFoundHttpException();
            }

            $station->updateAddress($data->name, $data->streetName, $data->postalCode, $data->city);
        } else {
            $station = Station::create(
                $data->name,
                $data->streetName,
                $data->postalCode,
                $data->city,
                null,
                null,
            );
        }

        $this->repository->save($station);

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
