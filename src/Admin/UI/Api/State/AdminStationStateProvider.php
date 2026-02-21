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
use ApiPlatform\State\ProviderInterface;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Enum\GeocodingStatus;
use App\Station\Domain\Station;
use App\Station\UI\Api\Resource\Output\StationOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use ValueError;

/**
 * @implements ProviderInterface<StationOutput>
 */
final readonly class AdminStationStateProvider implements ProviderInterface
{
    public function __construct(private StationRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $id = $uriVariables['id'] ?? null;
        if (null !== $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $station = $this->repository->getForSystem($id);
            if (!$station instanceof Station) {
                throw new NotFoundHttpException();
            }

            return $this->toOutput($station);
        }

        $query = $this->readFilter($context, 'q');
        $city = $this->readFilter($context, 'city');
        $status = $this->readFilter($context, 'geocodingStatus');

        $resources = [];
        foreach ($this->repository->allForSystem() as $station) {
            if (!$this->matchesFilters($station, $query, $city, $status)) {
                continue;
            }

            $resources[] = $this->toOutput($station);
        }

        return $resources;
    }

    private function toOutput(Station $station): StationOutput
    {
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

    private function matchesFilters(Station $station, ?string $query, ?string $city, ?string $status): bool
    {
        if (null !== $query) {
            $haystack = mb_strtolower(sprintf('%s %s %s %s', $station->name(), $station->streetName(), $station->postalCode(), $station->city()));
            if (!str_contains($haystack, mb_strtolower($query))) {
                return false;
            }
        }

        if (null !== $city && mb_strtolower($station->city()) !== mb_strtolower($city)) {
            return false;
        }

        if (null !== $status) {
            try {
                $expectedStatus = GeocodingStatus::from($status);
            } catch (ValueError) {
                return false;
            }

            if ($station->geocodingStatus() !== $expectedStatus) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $context */
    private function readFilter(array $context, string $name): ?string
    {
        $filters = $context['filters'] ?? null;
        if (!is_array($filters)) {
            return null;
        }

        $value = $filters[$name] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
