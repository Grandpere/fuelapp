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

use App\PublicFuelStation\Application\Admin\PublicFuelStationAdminDiagnosticsReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminPublicFuelStationDiagnosticsController extends AbstractController
{
    public function __construct(private readonly PublicFuelStationAdminDiagnosticsReader $diagnosticsReader)
    {
    }

    #[Route('/ui/admin/public-fuel-stations', name: 'ui_admin_public_fuel_station_diagnostics', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/public_fuel_stations/index.html.twig', [
            'diagnostics' => $this->diagnosticsReader->read(),
        ]);
    }
}
