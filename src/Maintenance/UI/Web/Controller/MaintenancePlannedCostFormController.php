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

use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\MaintenancePlannedCost;
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

final class MaintenancePlannedCostFormController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly MaintenancePlannedCostRepository $plannedCostRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/maintenance/plans/new', name: 'ui_maintenance_plan_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->handle($request, null);
    }

    #[Route('/ui/maintenance/plans/{id}/edit', name: 'ui_maintenance_plan_edit', methods: ['GET', 'POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
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

        $plan = null;
        if (is_string($id)) {
            $plan = $this->plannedCostRepository->get($id);
            if (!$plan instanceof MaintenancePlannedCost || $plan->ownerId() !== $ownerId) {
                throw new NotFoundHttpException();
            }
        }

        $formData = null === $plan ? $this->defaultFormData() : $this->formDataFromPlan($plan);
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData = $this->extractFormData($request);
            $errors = $this->validateFormData($formData, $ownerId);

            if ([] === $errors) {
                $vehicleId = trim($formData['vehicleId']);
                $label = trim($formData['label']);
                $eventType = '' === trim($formData['eventType']) ? null : MaintenanceEventType::from($formData['eventType']);
                $plannedFor = DateTimeImmutable::createFromFormat('Y-m-d', $formData['plannedFor']) ?: new DateTimeImmutable();
                $plannedCostCents = (int) $formData['plannedCostCents'];
                $currencyCode = strtoupper(trim($formData['currencyCode']));
                $notes = $this->nullIfEmpty($formData['notes']);

                if ($plan instanceof MaintenancePlannedCost) {
                    $plan->update($vehicleId, $label, $eventType, $plannedFor, $plannedCostCents, $currencyCode, $notes);
                } else {
                    $plan = MaintenancePlannedCost::create($ownerId, $vehicleId, $label, $eventType, $plannedFor, $plannedCostCents, $currencyCode, $notes);
                }

                $this->plannedCostRepository->save($plan);
                $this->addFlash('success', null === $id ? 'Planned cost created.' : 'Planned cost updated.');

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

        $response = $this->render('maintenance/plan_form.html.twig', [
            'isEdit' => null !== $id,
            'formData' => $formData,
            'errors' => $errors,
            'vehicleOptions' => $vehicleOptions,
            'eventTypes' => array_map(static fn (MaintenanceEventType $type): string => $type->value, MaintenanceEventType::cases()),
            'csrfToken' => $this->csrfTokenManager->getToken('maintenance_plan_form')->getValue(),
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
            'label' => '',
            'eventType' => MaintenanceEventType::SERVICE->value,
            'plannedFor' => new DateTimeImmutable('+7 days')->format('Y-m-d'),
            'plannedCostCents' => '',
            'currencyCode' => 'EUR',
            'notes' => '',
            '_token' => '',
        ];
    }

    /** @return array<string, string> */
    private function formDataFromPlan(MaintenancePlannedCost $plan): array
    {
        $eventType = $plan->eventType();

        return [
            'vehicleId' => $plan->vehicleId(),
            'label' => $plan->label(),
            'eventType' => null === $eventType ? '' : $eventType->value,
            'plannedFor' => $plan->plannedFor()->format('Y-m-d'),
            'plannedCostCents' => (string) $plan->plannedCostCents(),
            'currencyCode' => $plan->currencyCode(),
            'notes' => $plan->notes() ?? '',
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

        if (!$this->isCsrfTokenValid('maintenance_plan_form', $formData['_token'])) {
            $errors[] = 'Jeton CSRF invalide.';
        }

        $vehicleId = trim($formData['vehicleId']);
        if (!Uuid::isValid($vehicleId) || !$this->vehicleRepository->belongsToOwner($vehicleId, $ownerId)) {
            $errors[] = 'Vehicle not found.';
        }

        $label = trim($formData['label']);
        if ('' === $label) {
            $errors[] = 'Label is required.';
        }

        if ('' !== trim($formData['eventType']) && !in_array($formData['eventType'], array_map(static fn (MaintenanceEventType $type): string => $type->value, MaintenanceEventType::cases()), true)) {
            $errors[] = 'Invalid event type.';
        }

        $plannedFor = DateTimeImmutable::createFromFormat('Y-m-d', $formData['plannedFor']);
        if (false === $plannedFor) {
            $errors[] = 'Invalid planned date.';
        }

        $plannedCost = filter_var(trim($formData['plannedCostCents']), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (null === $plannedCost || $plannedCost <= 0) {
            $errors[] = 'Planned cost must be a positive integer.';
        }

        $currencyCode = strtoupper(trim($formData['currencyCode']));
        if (3 !== strlen($currencyCode)) {
            $errors[] = 'Currency code must contain exactly 3 letters.';
        }

        return array_values(array_unique($errors));
    }

    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
