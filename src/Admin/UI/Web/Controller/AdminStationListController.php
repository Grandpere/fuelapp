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

use App\Station\Application\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminStationListController extends AbstractController
{
    public function __construct(private readonly StationRepository $stationRepository)
    {
    }

    #[Route('/ui/admin/stations', name: 'ui_admin_station_list', methods: ['GET'])]
    public function __invoke(): Response
    {
        $stations = [];
        foreach ($this->stationRepository->allForSystem() as $station) {
            $stations[] = $station;
        }

        return $this->render('admin/stations/index.html.twig', [
            'stations' => $stations,
        ]);
    }
}
