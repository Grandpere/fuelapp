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

namespace App\Station\UI\Web\Controller;

use App\PublicFuelStation\Application\Matching\PublicFuelStationMatcher;
use App\PublicFuelStation\Application\Matching\VisitedStationPublicMatchQuery;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Security\Voter\StationVoter;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowStationController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly PublicFuelStationMatcher $publicFuelStationMatcher,
    ) {
    }

    #[Route('/ui/stations/{id}', name: 'ui_station_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(StationVoter::VIEW, $id);

        $station = $this->stationRepository->get($id);
        if (!$station instanceof Station) {
            throw $this->createNotFoundException('Station not found.');
        }

        $backToListUrl = $this->safeReturnTarget($request->query->get('return_to'));
        $receiptRows = $this->receiptRepository->paginateFilteredListRows(
            1,
            5,
            null,
            $station->id()->toString(),
            null,
            null,
            'date',
            'desc',
        );
        $receiptCount = $this->receiptRepository->countFiltered(
            null,
            $station->id()->toString(),
            null,
            null,
        );

        $latestReceipt = $receiptRows[0] ?? null;
        $recentFuelSpendCents = 0;
        $latestOdometer = null;
        foreach ($receiptRows as $row) {
            $recentFuelSpendCents += $row['totalCents'];
            if (null === $latestOdometer && is_int($row['odometerKilometers'])) {
                $latestOdometer = $row['odometerKilometers'];
            }
        }

        return $this->render('station/show.html.twig', [
            'station' => $station,
            'receiptRows' => $receiptRows,
            'receiptCount' => $receiptCount,
            'latestReceipt' => $latestReceipt,
            'recentFuelSpendCents' => $recentFuelSpendCents,
            'latestOdometer' => $latestOdometer,
            'publicStationCandidates' => $this->publicFuelStationMatcher->findCandidates(new VisitedStationPublicMatchQuery(
                $station->latitudeMicroDegrees(),
                $station->longitudeMicroDegrees(),
                $station->streetName(),
                $station->postalCode(),
                $station->city(),
            )),
            'backToListUrl' => $backToListUrl,
        ]);
    }

    private function safeReturnTarget(mixed $returnTo): string
    {
        if (is_string($returnTo) && '' !== $returnTo && str_starts_with($returnTo, '/')) {
            return $returnTo;
        }

        return $this->generateUrl('ui_receipt_index');
    }
}
