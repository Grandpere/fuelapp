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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class AdminStationDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private StationRepository $repository,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $station = $this->repository->getForSystem($id);
        if (!$station instanceof Station) {
            throw new NotFoundHttpException();
        }

        $this->repository->deleteForSystem($id);
        $this->auditTrail->record(
            'admin.station.deleted',
            'station',
            $id,
            [
                'before' => [
                    'name' => $station->name(),
                    'streetName' => $station->streetName(),
                    'postalCode' => $station->postalCode(),
                    'city' => $station->city(),
                ],
            ],
        );
    }
}
