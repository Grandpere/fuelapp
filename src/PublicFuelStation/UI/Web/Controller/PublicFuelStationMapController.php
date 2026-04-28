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

namespace App\PublicFuelStation\UI\Web\Controller;

use App\PublicFuelStation\Application\Search\PublicFuelStationListItem;
use App\PublicFuelStation\Application\Search\PublicFuelStationSearchFilters;
use App\PublicFuelStation\Application\Search\PublicFuelStationSearchReader;
use App\PublicFuelStation\Domain\Enum\PublicFuelType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicFuelStationMapController extends AbstractController
{
    public function __construct(private readonly PublicFuelStationSearchReader $stationSearchReader)
    {
    }

    #[Route('/ui/public-fuel-stations', name: 'ui_public_fuel_station_map', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $fuelType = $this->readFuelType($request->query->get('fuel'));
        $filters = new PublicFuelStationSearchFilters(
            $this->readString($request->query->get('q')),
            $fuelType,
            '0' !== $this->readString($request->query->get('available'), '1'),
        );
        $result = $this->stationSearchReader->search($filters);

        return $this->render('public_fuel_station/index.html.twig', [
            'filters' => [
                'q' => $filters->query ?? '',
                'fuel' => $fuelType instanceof PublicFuelType ? $fuelType->value : '',
                'available' => $filters->availableOnly ? '1' : '0',
            ],
            'fuelOptions' => PublicFuelType::cases(),
            'result' => $result,
            'mapPoints' => array_map(fn (PublicFuelStationListItem $item): array => $this->mapPoint($item, $fuelType), $result->items),
            'selectedFuelType' => $fuelType,
        ]);
    }

    private function readFuelType(mixed $value): ?PublicFuelType
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        return PublicFuelType::tryFrom(trim($value));
    }

    private function readString(mixed $value, string $default = ''): string
    {
        if (!is_string($value)) {
            return $default;
        }

        return trim($value);
    }

    /** @return array<string, bool|float|int|string|null> */
    private function mapPoint(PublicFuelStationListItem $item, ?PublicFuelType $selectedFuelType): array
    {
        $fuelKey = $selectedFuelType?->value;
        $snapshot = null !== $fuelKey ? ($item->fuels[$fuelKey] ?? null) : null;

        return [
            'sourceId' => $item->sourceId,
            'latitude' => $item->latitude,
            'longitude' => $item->longitude,
            'label' => trim($item->postalCode.' '.$item->city),
            'address' => $item->address,
            'automate24' => $item->automate24,
            'fuel' => $selectedFuelType?->sourceLabel(),
            'priceMilliEurosPerLiter' => is_array($snapshot) ? ($snapshot['priceMilliEurosPerLiter'] ?? null) : null,
            'available' => is_array($snapshot) ? ($snapshot['available'] ?? null) : null,
        ];
    }
}
