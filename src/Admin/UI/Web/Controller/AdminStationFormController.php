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

use App\Admin\Application\Audit\AdminAuditTrail;
use App\Station\Application\Command\UpdateStationAddressCommand;
use App\Station\Application\Command\UpdateStationAddressHandler;
use App\Station\Application\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

final class AdminStationFormController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly UpdateStationAddressHandler $updateStationAddressHandler,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/ui/admin/stations/{id}/edit', name: 'ui_admin_station_edit', methods: ['GET', 'POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function edit(Request $request, string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $station = $this->stationRepository->getForSystem($id);
        if (null === $station) {
            throw new NotFoundHttpException();
        }

        $formData = [
            'name' => $station->name(),
            'streetName' => $station->streetName(),
            'postalCode' => $station->postalCode(),
            'city' => $station->city(),
            '_token' => '',
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $nameValue = $request->request->get('name', '');
            $streetNameValue = $request->request->get('streetName', '');
            $postalCodeValue = $request->request->get('postalCode', '');
            $cityValue = $request->request->get('city', '');
            $tokenValue = $request->request->get('_token', '');

            $formData = [
                'name' => is_scalar($nameValue) ? trim((string) $nameValue) : '',
                'streetName' => is_scalar($streetNameValue) ? trim((string) $streetNameValue) : '',
                'postalCode' => is_scalar($postalCodeValue) ? trim((string) $postalCodeValue) : '',
                'city' => is_scalar($cityValue) ? trim((string) $cityValue) : '',
                '_token' => is_scalar($tokenValue) ? (string) $tokenValue : '',
            ];

            if (!$this->isCsrfTokenValid('admin_station_form', $formData['_token'])) {
                $errors[] = 'Invalid CSRF token.';
            }

            foreach (['name', 'streetName', 'postalCode', 'city'] as $field) {
                if ('' === $formData[$field]) {
                    $errors[] = sprintf('Field "%s" is required.', $field);
                }
            }

            if ([] === $errors) {
                $before = [
                    'name' => $station->name(),
                    'streetName' => $station->streetName(),
                    'postalCode' => $station->postalCode(),
                    'city' => $station->city(),
                ];

                $updated = ($this->updateStationAddressHandler)(new UpdateStationAddressCommand(
                    $id,
                    $formData['name'],
                    $formData['streetName'],
                    $formData['postalCode'],
                    $formData['city'],
                ));

                if (null !== $updated) {
                    $this->auditTrail->record(
                        'admin.station.updated.ui',
                        'station',
                        $id,
                        [
                            'before' => $before,
                            'after' => [
                                'name' => $updated->name(),
                                'streetName' => $updated->streetName(),
                                'postalCode' => $updated->postalCode(),
                                'city' => $updated->city(),
                            ],
                        ],
                    );
                }

                $this->addFlash('success', 'Station updated.');

                return new RedirectResponse($this->generateUrl('ui_admin_station_list'), Response::HTTP_SEE_OTHER);
            }
        }

        $response = $this->render('admin/stations/form.html.twig', [
            'station' => $station,
            'formData' => $formData,
            'errors' => $errors,
            'csrfToken' => $this->csrfTokenManager->getToken('admin_station_form')->getValue(),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }
}
