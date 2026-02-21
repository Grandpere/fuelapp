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
use App\Vehicle\Domain\Vehicle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class VehicleDeleteController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    #[Route('/ui/vehicles/{id}/delete', name: 'ui_vehicle_delete', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $vehicle = $this->vehicleRepository->get($id);
        if (!$vehicle instanceof Vehicle || $vehicle->ownerId() !== $ownerId) {
            throw new NotFoundHttpException();
        }

        $token = $request->request->get('_token');
        if (!is_scalar($token) || !$this->isCsrfTokenValid('vehicle_delete_'.$id, (string) $token)) {
            throw new NotFoundHttpException();
        }

        $this->vehicleRepository->delete($id);
        $this->addFlash('success', 'Vehicle deleted.');

        return new RedirectResponse($this->generateUrl('ui_vehicle_list'), Response::HTTP_SEE_OTHER);
    }
}
