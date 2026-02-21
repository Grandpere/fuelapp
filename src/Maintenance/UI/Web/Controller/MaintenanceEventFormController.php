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

namespace App\Maintenance\UI\Web\Controller;

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\MaintenanceEvent;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

final class MaintenanceEventFormController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly MaintenanceEventRepository $eventRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/maintenance/events/new', name: 'ui_maintenance_event_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->handle($request, null);
    }

    #[Route('/ui/maintenance/events/{id}/edit', name: 'ui_maintenance_event_edit', methods: ['GET', 'POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
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

        $event = null;
        if (is_string($id)) {
            $event = $this->eventRepository->get($id);
            if (!$event instanceof MaintenanceEvent || $event->ownerId() !== $ownerId) {
                throw new NotFoundHttpException();
            }
        }

        $formData = null === $event ? $this->defaultFormData() : $this->formDataFromEvent($event);
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData = $this->extractFormData($request);
            $errors = $this->validateFormData($formData, $ownerId);

            if ([] === $errors) {
                $vehicleId = trim($formData['vehicleId']);
                $eventType = MaintenanceEventType::from($formData['eventType']);
                $occurredAt = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $formData['occurredAt']) ?: new DateTimeImmutable();
                $description = $this->nullIfEmpty($formData['description']);
                $odometer = $this->nullableInt($formData['odometerKilometers']);
                $totalCost = $this->nullableInt($formData['totalCostCents']);
                $currencyCode = strtoupper(trim($formData['currencyCode']));

                if ($event instanceof MaintenanceEvent) {
                    $event->update($vehicleId, $eventType, $occurredAt, $description, $odometer, $totalCost, $currencyCode);
                } else {
                    $event = MaintenanceEvent::create($ownerId, $vehicleId, $eventType, $occurredAt, $description, $odometer, $totalCost, $currencyCode);
                }

                $this->eventRepository->save($event);
                $this->addFlash('success', null === $id ? 'Maintenance event created.' : 'Maintenance event updated.');

                return new RedirectResponse($this->generateUrl('ui_maintenance_index'), Response::HTTP_SEE_OTHER);
            }
        }

        $vehicleOptions = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            if ($vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $vehicleOptions[$vehicle->id()->toString()] = sprintf('%s (%s)', $vehicle->name(), $vehicle->plateNumber());
        }

        $response = $this->render('maintenance/event_form.html.twig', [
            'isEdit' => null !== $id,
            'formData' => $formData,
            'errors' => $errors,
            'vehicleOptions' => $vehicleOptions,
            'eventTypes' => array_map(static fn (MaintenanceEventType $type): string => $type->value, MaintenanceEventType::cases()),
            'csrfToken' => $this->csrfTokenManager->getToken('maintenance_event_form')->getValue(),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /** @return array<string, string> */
    private function defaultFormData(): array
    {
        return [
            'vehicleId' => '',
            'eventType' => MaintenanceEventType::SERVICE->value,
            'occurredAt' => new DateTimeImmutable()->format('Y-m-d\\TH:i'),
            'description' => '',
            'odometerKilometers' => '',
            'totalCostCents' => '',
            'currencyCode' => 'EUR',
            '_token' => '',
        ];
    }

    /** @return array<string, string> */
    private function formDataFromEvent(MaintenanceEvent $event): array
    {
        return [
            'vehicleId' => $event->vehicleId(),
            'eventType' => $event->eventType()->value,
            'occurredAt' => $event->occurredAt()->format('Y-m-d\\TH:i'),
            'description' => $event->description() ?? '',
            'odometerKilometers' => null === $event->odometerKilometers() ? '' : (string) $event->odometerKilometers(),
            'totalCostCents' => null === $event->totalCostCents() ? '' : (string) $event->totalCostCents(),
            'currencyCode' => $event->currencyCode(),
            '_token' => '',
        ];
    }

    /** @return array<string, string> */
    private function extractFormData(Request $request): array
    {
        $data = $this->defaultFormData();
        foreach (array_keys($data) as $key) {
            $value = $request->request->get($key, '');
            $data[$key] = is_scalar($value) ? (string) $value : '';
        }

        return $data;
    }

    /**
     * @param array<string, string> $formData
     *
     * @return list<string>
     */
    private function validateFormData(array $formData, string $ownerId): array
    {
        $errors = [];

        if (!$this->isCsrfTokenValid('maintenance_event_form', $formData['_token'])) {
            $errors[] = 'Jeton CSRF invalide.';
        }

        $vehicleId = trim($formData['vehicleId']);
        if (!Uuid::isValid($vehicleId) || !$this->vehicleRepository->belongsToOwner($vehicleId, $ownerId)) {
            $errors[] = 'Vehicle not found.';
        }

        if (!in_array($formData['eventType'], array_map(static fn (MaintenanceEventType $type): string => $type->value, MaintenanceEventType::cases()), true)) {
            $errors[] = 'Invalid event type.';
        }

        $occurredAt = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $formData['occurredAt']);
        if (false === $occurredAt) {
            $errors[] = 'Invalid event date.';
        }

        foreach (['odometerKilometers', 'totalCostCents'] as $field) {
            $value = $this->nullableInt($formData[$field]);
            if ('' !== trim($formData[$field]) && null === $value) {
                $errors[] = sprintf('Field %s must be an integer.', $field);
                continue;
            }

            if (null !== $value && $value < 0) {
                $errors[] = sprintf('Field %s must be non-negative.', $field);
            }
        }

        $currencyCode = strtoupper(trim($formData['currencyCode']));
        if (3 !== strlen($currencyCode)) {
            $errors[] = 'Currency code must contain exactly 3 letters.';
        }

        return array_values(array_unique($errors));
    }

    private function nullableInt(string $value): ?int
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        $intValue = filter_var($trimmed, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return null === $intValue ? null : $intValue;
    }

    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
