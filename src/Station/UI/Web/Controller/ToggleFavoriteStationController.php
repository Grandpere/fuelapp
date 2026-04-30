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

use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Shared\UI\Web\SafeReturnPathResolver;
use App\Station\Application\Favorite\ToggleFavoriteStationCommand;
use App\Station\Application\Favorite\ToggleFavoriteStationHandler;
use App\Station\Application\Repository\StationRepository;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ToggleFavoriteStationController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ToggleFavoriteStationHandler $toggleFavoriteStationHandler,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly SafeReturnPathResolver $safeReturnPathResolver,
        private readonly StationRepository $stationRepository,
    ) {
    }

    #[Route('/ui/stations/{id}/toggle-favorite', name: 'ui_station_toggle_favorite', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw $this->createNotFoundException();
        }

        if (null === $this->stationRepository->get($id)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('station_favorite_toggle_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('ui_station_list');
        }

        try {
            $isFavorite = ($this->toggleFavoriteStationHandler)(new ToggleFavoriteStationCommand($ownerId, $id));
        } catch (InvalidArgumentException) {
            throw $this->createNotFoundException();
        }

        $this->addFlash('success', $isFavorite ? 'Station added to favorites.' : 'Station removed from favorites.');

        return new RedirectResponse($this->safeReturnPathResolver->resolve(
            $request->request->get('_redirect'),
            $this->generateUrl('ui_station_list'),
        ));
    }
}
