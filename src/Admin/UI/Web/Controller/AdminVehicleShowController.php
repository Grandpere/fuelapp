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

namespace App\Admin\UI\Web\Controller;

use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminVehicleShowController extends AbstractController
{
    public function __construct(private readonly VehicleRepository $vehicleRepository)
    {
    }

    #[Route('/ui/admin/vehicles/{id}', name: 'ui_admin_vehicle_show', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $vehicle = $this->vehicleRepository->get($id);
        if (null === $vehicle) {
            throw new NotFoundHttpException();
        }

        return $this->render('admin/vehicles/show.html.twig', [
            'vehicle' => $vehicle,
        ]);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
