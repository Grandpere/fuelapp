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

namespace App\Vehicle\UI\Web\Controller;

use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class VehicleListController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    #[Route('/ui/vehicles', name: 'ui_vehicle_list', methods: ['GET'])]
    public function __invoke(): Response
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $vehicles = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            if ($vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $vehicles[] = $vehicle;
        }

        usort($vehicles, static fn ($a, $b): int => strcmp($a->name(), $b->name()));

        return $this->render('vehicle/index.html.twig', [
            'vehicles' => $vehicles,
        ]);
    }
}
