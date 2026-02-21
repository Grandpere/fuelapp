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
use App\Admin\Application\Audit\AdminAuditTrail;
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
    public function __construct(
        private StationRepository $repository,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StationOutput
    {
        if (!$data instanceof UpdateStationAddressInput) {
            throw new InvalidArgumentException('Invalid station input.');
        }

        $id = $uriVariables['id'] ?? null;
        $station = null;
        $action = 'admin.station.created';
        $before = [];

        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $station = $this->repository->getForSystem($id);
            if (!$station instanceof Station) {
                throw new NotFoundHttpException();
            }

            $action = 'admin.station.updated';
            $before = $this->snapshot($station);
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
        $after = $this->snapshot($station);
        $this->auditTrail->record(
            $action,
            'station',
            $station->id()->toString(),
            [
                'before' => $before,
                'after' => $after,
                'changed' => $this->diff($before, $after),
            ],
        );

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

    /** @return array<string, mixed> */
    private function snapshot(Station $station): array
    {
        return [
            'name' => $station->name(),
            'streetName' => $station->streetName(),
            'postalCode' => $station->postalCode(),
            'city' => $station->city(),
            'latitudeMicroDegrees' => $station->latitudeMicroDegrees(),
            'longitudeMicroDegrees' => $station->longitudeMicroDegrees(),
            'geocodingStatus' => $station->geocodingStatus()->value,
        ];
    }

    /** @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;
            if ($previous !== $value) {
                $changed[$key] = ['before' => $previous, 'after' => $value];
            }
        }

        return $changed;
    }
}
