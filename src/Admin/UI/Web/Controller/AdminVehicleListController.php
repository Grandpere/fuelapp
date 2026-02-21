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
use Symfony\Component\Routing\Attribute\Route;

final class AdminVehicleListController extends AbstractController
{
    public function __construct(private readonly VehicleRepository $vehicleRepository)
    {
    }

    #[Route('/ui/admin/vehicles', name: 'ui_admin_vehicle_list', methods: ['GET'])]
    public function __invoke(): Response
    {
        $vehicles = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            $vehicles[] = $vehicle;
        }

        return $this->render('admin/vehicles/index.html.twig', [
            'vehicles' => $vehicles,
        ]);
    }
}
