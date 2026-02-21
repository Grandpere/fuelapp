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

use App\Security\Voter\StationVoter;
use App\Station\Application\Command\UpdateStationAddressCommand;
use App\Station\Application\Command\UpdateStationAddressHandler;
use App\Station\Application\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EditStationController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly UpdateStationAddressHandler $updateStationAddressHandler,
    ) {
    }

    #[Route('/ui/stations/{id}/edit', name: 'ui_station_edit', methods: ['GET', 'POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(StationVoter::EDIT, $id);

        $station = $this->stationRepository->get($id);
        if (null === $station) {
            throw $this->createNotFoundException('Station not found.');
        }

        $redirect = $this->safeRedirectTarget($request->query->get('redirect'));
        $errors = [];
        $formData = [
            'name' => $station->name(),
            'streetName' => $station->streetName(),
            'postalCode' => $station->postalCode(),
            'city' => $station->city(),
            '_token' => '',
        ];

        if ($request->isMethod('POST')) {
            $formData = [
                'name' => trim((string) $request->request->get('name', '')),
                'streetName' => trim((string) $request->request->get('streetName', '')),
                'postalCode' => trim((string) $request->request->get('postalCode', '')),
                'city' => trim((string) $request->request->get('city', '')),
                '_token' => (string) $request->request->get('_token', ''),
            ];

            if (!$this->isCsrfTokenValid('station_edit_'.$id, $formData['_token'])) {
                $errors[] = 'Invalid CSRF token.';
            }

            foreach (['name', 'streetName', 'postalCode', 'city'] as $field) {
                if ('' === $formData[$field]) {
                    $errors[] = sprintf('Field "%s" is required.', $field);
                }
            }

            if ([] === $errors) {
                ($this->updateStationAddressHandler)(new UpdateStationAddressCommand(
                    $id,
                    $formData['name'],
                    $formData['streetName'],
                    $formData['postalCode'],
                    $formData['city'],
                ));

                $this->addFlash('success', 'Station updated.');

                return new RedirectResponse($redirect, Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('station/edit.html.twig', [
            'stationId' => $id,
            'formData' => $formData,
            'errors' => $errors,
            'redirect' => $redirect,
        ]);
    }

    private function safeRedirectTarget(mixed $redirect): string
    {
        if (is_string($redirect) && '' !== $redirect && str_starts_with($redirect, '/')) {
            return $redirect;
        }

        return $this->generateUrl('ui_receipt_index');
    }
}
