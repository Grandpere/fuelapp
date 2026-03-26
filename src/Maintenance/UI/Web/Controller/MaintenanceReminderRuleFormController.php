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

use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Domain\MaintenanceReminderRule;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use ValueError;

final class MaintenanceReminderRuleFormController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly MaintenanceReminderRuleRepository $ruleRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/maintenance/rules/new', name: 'ui_maintenance_rule_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->handle($request, null);
    }

    #[Route('/ui/maintenance/rules/{id}/edit', name: 'ui_maintenance_rule_edit', methods: ['GET', 'POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
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

        $rule = null;
        if (is_string($id)) {
            $rule = $this->ruleRepository->get($id);
            if (!$rule instanceof MaintenanceReminderRule || $rule->ownerId() !== $ownerId) {
                throw new NotFoundHttpException();
            }
        }

        $formData = null === $rule ? $this->defaultFormData($request, $ownerId) : $this->formDataFromRule($rule);
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData = $this->extractFormData($request, $ownerId);
            $errors = $this->validateFormData($formData, $ownerId);

            if ([] === $errors) {
                $vehicleId = trim($formData['vehicleId']);
                $name = trim($formData['name']);
                $triggerMode = ReminderRuleTriggerMode::from($formData['triggerMode']);
                $eventType = '' === trim($formData['eventType']) ? null : MaintenanceEventType::from($formData['eventType']);
                $intervalDays = $this->nullableInt($formData['intervalDays']);
                $intervalKilometers = $this->nullableInt($formData['intervalKilometers']);

                if ($rule instanceof MaintenanceReminderRule) {
                    $rule->update($vehicleId, $name, $triggerMode, $eventType, $intervalDays, $intervalKilometers);
                } else {
                    $rule = MaintenanceReminderRule::create($ownerId, $vehicleId, $name, $triggerMode, $eventType, $intervalDays, $intervalKilometers);
                }

                $this->ruleRepository->save($rule);
                $this->addFlash('success', null === $id ? 'Reminder rule created.' : 'Reminder rule updated.');

                return new RedirectResponse($this->generateUrl('ui_maintenance_index', ['vehicle_id' => $vehicleId]), Response::HTTP_SEE_OTHER);
            }
        }

        $vehicleOptions = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            if ($vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $vehicleOptions[$vehicle->id()->toString()] = sprintf('%s (%s)', $vehicle->name(), $vehicle->plateNumber());
        }

        $response = $this->render('maintenance/rule_form.html.twig', [
            'isEdit' => null !== $id,
            'formData' => $formData,
            'errors' => $errors,
            'vehicleOptions' => $vehicleOptions,
            'triggerModes' => array_map(static fn (ReminderRuleTriggerMode $mode): string => $mode->value, ReminderRuleTriggerMode::cases()),
            'eventTypes' => array_map(static fn (MaintenanceEventType $type): string => $type->value, MaintenanceEventType::cases()),
            'csrfToken' => $this->csrfTokenManager->getToken('maintenance_rule_form')->getValue(),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /** @return array<string, string> */
    private function defaultFormData(Request $request, string $ownerId): array
    {
        return [
            'vehicleId' => $this->readPrefilledVehicleId($request, $ownerId) ?? '',
            'name' => '',
            'triggerMode' => ReminderRuleTriggerMode::DATE->value,
            'eventType' => MaintenanceEventType::SERVICE->value,
            'intervalDays' => '180',
            'intervalKilometers' => '',
            '_token' => '',
        ];
    }

    /** @return array<string, string> */
    private function formDataFromRule(MaintenanceReminderRule $rule): array
    {
        return [
            'vehicleId' => $rule->vehicleId(),
            'name' => $rule->name(),
            'triggerMode' => $rule->triggerMode()->value,
            'eventType' => null === $rule->eventType() ? '' : $rule->eventType()->value,
            'intervalDays' => null === $rule->intervalDays() ? '' : (string) $rule->intervalDays(),
            'intervalKilometers' => null === $rule->intervalKilometers() ? '' : (string) $rule->intervalKilometers(),
            '_token' => '',
        ];
    }

    /** @return array<string, string> */
    private function extractFormData(Request $request, string $ownerId): array
    {
        $data = $this->defaultFormData($request, $ownerId);
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

        if (!$this->isCsrfTokenValid('maintenance_rule_form', $formData['_token'])) {
            $errors[] = 'Jeton CSRF invalide.';
        }

        $vehicleId = trim($formData['vehicleId']);
        if (!Uuid::isValid($vehicleId) || !$this->vehicleRepository->belongsToOwner($vehicleId, $ownerId)) {
            $errors[] = 'Vehicle not found.';
        }

        if ('' === trim($formData['name'])) {
            $errors[] = 'Rule name is required.';
        }

        try {
            $triggerMode = ReminderRuleTriggerMode::from($formData['triggerMode']);
        } catch (ValueError) {
            $errors[] = 'Invalid trigger mode.';
            $triggerMode = null;
        }

        if ('' !== trim($formData['eventType'])) {
            try {
                MaintenanceEventType::from($formData['eventType']);
            } catch (ValueError) {
                $errors[] = 'Invalid event type.';
            }
        }

        $intervalDays = $this->nullableInt($formData['intervalDays']);
        $intervalKilometers = $this->nullableInt($formData['intervalKilometers']);

        if ('' !== trim($formData['intervalDays']) && (null === $intervalDays || $intervalDays <= 0)) {
            $errors[] = 'Days interval must be a positive integer.';
        }
        if ('' !== trim($formData['intervalKilometers']) && (null === $intervalKilometers || $intervalKilometers <= 0)) {
            $errors[] = 'Kilometers interval must be a positive integer.';
        }

        if ($triggerMode instanceof ReminderRuleTriggerMode) {
            if (ReminderRuleTriggerMode::DATE === $triggerMode && null === $intervalDays) {
                $errors[] = 'DATE trigger requires a days interval.';
            }
            if (ReminderRuleTriggerMode::ODOMETER === $triggerMode && null === $intervalKilometers) {
                $errors[] = 'ODOMETER trigger requires a kilometers interval.';
            }
            if (ReminderRuleTriggerMode::WHICHEVER_FIRST === $triggerMode && (null === $intervalDays || null === $intervalKilometers)) {
                $errors[] = 'WHICHEVER_FIRST trigger requires both days and kilometers intervals.';
            }
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

    private function readPrefilledVehicleId(Request $request, string $ownerId): ?string
    {
        $raw = $request->query->get('vehicle_id');
        if (!is_scalar($raw)) {
            return null;
        }

        $vehicleId = trim((string) $raw);
        if ('' === $vehicleId || !Uuid::isValid($vehicleId)) {
            return null;
        }

        return $this->vehicleRepository->belongsToOwner($vehicleId, $ownerId) ? $vehicleId : null;
    }
}
