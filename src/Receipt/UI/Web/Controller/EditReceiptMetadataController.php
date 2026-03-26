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

namespace App\Receipt\UI\Web\Controller;

use App\Receipt\Application\Command\UpdateReceiptMetadataCommand;
use App\Receipt\Application\Command\UpdateReceiptMetadataHandler;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Security\Voter\ReceiptVoter;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Shared\UI\Web\SafeReturnPathResolver;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

final class EditReceiptMetadataController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly UpdateReceiptMetadataHandler $updateReceiptMetadataHandler,
        private readonly VehicleRepository $vehicleRepository,
        private readonly StationRepository $stationRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly SafeReturnPathResolver $safeReturnPathResolver,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/receipts/{id}/edit-metadata', name: 'ui_receipt_edit_metadata', methods: ['GET', 'POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): Response
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(ReceiptVoter::VIEW, $id);

        $receipt = $this->receiptRepository->get($id);
        if (null === $receipt) {
            throw $this->createNotFoundException('Receipt not found.');
        }

        $backToListUrl = $this->safeReturnPathResolver->resolve(
            $request->isMethod('POST') ? $request->request->get('_return_to') : $request->query->get('return_to'),
            $this->generateUrl('ui_receipt_index'),
        );

        $formData = [
            'issuedAt' => $receipt->issuedAt()->format('Y-m-d\TH:i'),
            'vehicleId' => $receipt->vehicleId()?->toString() ?? '',
            'stationId' => $receipt->stationId()?->toString() ?? '',
            'odometerKilometers' => null === $receipt->odometerKilometers() ? '' : (string) $receipt->odometerKilometers(),
            '_token' => '',
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData['_token'] = (string) $request->request->get('_token', '');
            $formData['issuedAt'] = trim((string) $request->request->get('issuedAt', ''));
            $formData['vehicleId'] = trim((string) $request->request->get('vehicleId', ''));
            $formData['stationId'] = trim((string) $request->request->get('stationId', ''));
            $formData['odometerKilometers'] = trim((string) $request->request->get('odometerKilometers', ''));

            if (!$this->isCsrfTokenValid('receipt_edit_metadata_'.$id, $formData['_token'])) {
                $errors[] = 'Invalid CSRF token.';
            }

            $issuedAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $formData['issuedAt']) ?: null;
            if (null === $issuedAt) {
                $errors[] = 'Invalid issue date.';
            }

            $vehicleId = $this->nullIfEmpty($formData['vehicleId']);
            $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
            if (null !== $vehicleId && (null === $ownerId || !$this->vehicleRepository->belongsToOwner($vehicleId, $ownerId))) {
                $errors[] = 'Vehicle not found.';
            }

            $stationId = $this->nullIfEmpty($formData['stationId']);
            if (null !== $stationId && null === $this->stationRepository->getForSystem($stationId)) {
                $errors[] = 'Station not found.';
            }

            $odometerKilometers = $this->toNullableInt($formData['odometerKilometers']);
            if ('' !== $formData['odometerKilometers'] && null === $odometerKilometers) {
                $errors[] = 'Odometer must be a positive integer.';
            }

            if ([] === $errors && $issuedAt instanceof DateTimeImmutable) {
                $updated = ($this->updateReceiptMetadataHandler)(new UpdateReceiptMetadataCommand(
                    $id,
                    $issuedAt,
                    $stationId,
                    $vehicleId,
                    $odometerKilometers,
                ));

                if (null === $updated) {
                    throw $this->createNotFoundException('Receipt not found.');
                }

                $this->addFlash('success', 'Receipt details updated.');

                return new RedirectResponse($this->generateUrl('ui_receipt_show', [
                    'id' => $id,
                    'return_to' => $backToListUrl,
                ]), Response::HTTP_SEE_OTHER);
            }
        }

        $response = $this->render('receipt/edit_metadata.html.twig', [
            'receipt' => $receipt,
            'formData' => $formData,
            'errors' => $errors,
            'vehicleOptions' => $this->vehicleOptions(),
            'stationOptions' => $this->stationOptions(),
            'backToListUrl' => $backToListUrl,
            'csrfToken' => $this->csrfTokenManager->getToken('receipt_edit_metadata_'.$id)->getValue(),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /** @return array<string, string> */
    private function vehicleOptions(): array
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            return [];
        }

        $options = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            if ($vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $options[$vehicle->id()->toString()] = sprintf('%s (%s)', $vehicle->name(), $vehicle->plateNumber());
        }

        return $options;
    }

    /** @return array<string, string> */
    private function stationOptions(): array
    {
        $options = [];
        foreach ($this->stationRepository->allForSystem() as $station) {
            $options[$station->id()->toString()] = sprintf('%s, %s %s', $station->name(), $station->postalCode(), $station->city());
        }

        return $options;
    }

    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function toNullableInt(string $value): ?int
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        $int = filter_var($trimmed, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (null === $int || $int < 0) {
            return null;
        }

        return $int;
    }
}
