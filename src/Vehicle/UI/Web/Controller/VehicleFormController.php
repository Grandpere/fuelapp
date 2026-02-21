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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

final class VehicleFormController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/vehicles/new', name: 'ui_vehicle_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->handle($request, null);
    }

    #[Route('/ui/vehicles/{id}/edit', name: 'ui_vehicle_edit', methods: ['GET', 'POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function edit(Request $request, string $id): Response
    {
        return $this->handle($request, $id);
    }

    private function handle(Request $request, ?string $id): Response
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $vehicle = null;
        if (null !== $id) {
            if (!Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $vehicle = $this->vehicleRepository->get($id);
            if (!$vehicle instanceof Vehicle || $vehicle->ownerId() !== $ownerId) {
                throw new NotFoundHttpException();
            }
        }

        $formData = null === $vehicle
            ? ['name' => '', 'plateNumber' => '', '_token' => '']
            : ['name' => $vehicle->name(), 'plateNumber' => $vehicle->plateNumber(), '_token' => ''];
        $errors = [];

        if ($request->isMethod('POST')) {
            $nameValue = $request->request->get('name', '');
            $plateValue = $request->request->get('plateNumber', '');
            $tokenValue = $request->request->get('_token', '');

            $formData = [
                'name' => is_scalar($nameValue) ? (string) $nameValue : '',
                'plateNumber' => is_scalar($plateValue) ? (string) $plateValue : '',
                '_token' => is_scalar($tokenValue) ? (string) $tokenValue : '',
            ];

            $errors = $this->validate($formData, $ownerId, $vehicle);
            if ([] === $errors) {
                $name = trim($formData['name']);
                $plateNumber = trim($formData['plateNumber']);

                if ($vehicle instanceof Vehicle) {
                    $vehicle->update($ownerId, $name, $plateNumber);
                    $this->vehicleRepository->save($vehicle);
                    $this->addFlash('success', 'Vehicle updated.');
                } else {
                    $newVehicle = Vehicle::create($ownerId, $name, $plateNumber);
                    $this->vehicleRepository->save($newVehicle);
                    $this->addFlash('success', 'Vehicle created.');
                }

                return new RedirectResponse($this->generateUrl('ui_vehicle_list'), Response::HTTP_SEE_OTHER);
            }
        }

        $response = $this->render('vehicle/form.html.twig', [
            'isEdit' => null !== $vehicle,
            'formData' => $formData,
            'errors' => $errors,
            'csrfToken' => $this->csrfTokenManager->getToken('vehicle_form')->getValue(),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /**
     * @param array{name:string,plateNumber:string,_token:string} $formData
     *
     * @return list<string>
     */
    private function validate(array $formData, string $ownerId, ?Vehicle $currentVehicle): array
    {
        $errors = [];

        if (!$this->isCsrfTokenValid('vehicle_form', $formData['_token'])) {
            $errors[] = 'Jeton CSRF invalide.';
        }

        $name = trim($formData['name']);
        if ('' === $name) {
            $errors[] = 'Name is required.';
        }

        $plateNumber = trim($formData['plateNumber']);
        if ('' === $plateNumber) {
            $errors[] = 'Plate number is required.';
        }

        if ('' !== $plateNumber) {
            $existing = $this->vehicleRepository->findByOwnerAndPlateNumber($ownerId, $plateNumber);
            if ($existing instanceof Vehicle && (null === $currentVehicle || $existing->id()->toString() !== $currentVehicle->id()->toString())) {
                $errors[] = 'A vehicle with this plate already exists for this owner.';
            }
        }

        return array_values(array_unique($errors));
    }
}
