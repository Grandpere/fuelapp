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

use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\MaintenanceReminder;
use App\Maintenance\Domain\MaintenanceReminderRule;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminMaintenanceReminderShowController extends AbstractController
{
    public function __construct(
        private readonly MaintenanceReminderRepository $reminderRepository,
        private readonly MaintenanceReminderRuleRepository $ruleRepository,
        private readonly VehicleRepository $vehicleRepository,
    ) {
    }

    #[Route('/ui/admin/maintenance/reminders/{id}', name: 'ui_admin_maintenance_reminder_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $reminder = null;
        foreach ($this->reminderRepository->allForSystem() as $item) {
            if ($item->id()->toString() === $id) {
                $reminder = $item;
                break;
            }
        }

        if (!$reminder instanceof MaintenanceReminder) {
            throw new NotFoundHttpException();
        }

        $rule = $this->ruleRepository->get($reminder->ruleId());
        $ruleName = $rule?->name() ?? $reminder->ruleId();

        $vehicle = $this->vehicleRepository->get($reminder->vehicleId());
        $requestedReturnTo = $request->query->get('return_to');
        $backToListUrl = is_string($requestedReturnTo) && '' !== trim($requestedReturnTo) && str_starts_with($requestedReturnTo, '/') && !str_starts_with($requestedReturnTo, '//')
            ? $requestedReturnTo
            : $this->generateUrl('ui_admin_maintenance_reminder_list');

        return $this->render('admin/maintenance/reminders/show.html.twig', [
            'reminder' => $reminder,
            'ruleName' => $ruleName,
            'vehicle' => $vehicle,
            'backToListUrl' => $backToListUrl,
            'ruleSummary' => $this->buildRuleSummary($rule),
            'matchingEventsUrl' => $this->buildMatchingEventsUrl($reminder->vehicleId(), $rule),
            'matchingReceiptsUrl' => $this->generateUrl('ui_admin_receipt_list', ['vehicle_id' => $reminder->vehicleId()]),
            'matchingRemindersUrl' => $this->buildMatchingRemindersUrl($reminder->vehicleId(), $rule),
        ]);
    }

    /** @return array{triggerMode:?string,eventType:?string,cadence:?string} */
    private function buildRuleSummary(?MaintenanceReminderRule $rule): array
    {
        if (!$rule instanceof MaintenanceReminderRule) {
            return [
                'triggerMode' => null,
                'eventType' => null,
                'cadence' => null,
            ];
        }

        return [
            'triggerMode' => $rule->triggerMode()->value,
            'eventType' => $rule->eventType()?->value,
            'cadence' => $this->formatCadence($rule),
        ];
    }

    private function buildMatchingEventsUrl(string $vehicleId, ?MaintenanceReminderRule $rule): string
    {
        $parameters = [
            'vehicle_id' => $vehicleId,
        ];

        if ($rule instanceof MaintenanceReminderRule && null !== $rule->eventType()) {
            $parameters['event_type'] = $rule->eventType()->value;
        }

        return $this->generateUrl('ui_admin_maintenance_event_list', $parameters);
    }

    private function buildMatchingRemindersUrl(string $vehicleId, ?MaintenanceReminderRule $rule): string
    {
        $parameters = [
            'vehicle_id' => $vehicleId,
        ];

        if ($rule instanceof MaintenanceReminderRule && null !== $rule->eventType()) {
            $parameters['event_type'] = $rule->eventType()->value;
        }

        return $this->generateUrl('ui_admin_maintenance_reminder_list', $parameters);
    }

    private function formatCadence(MaintenanceReminderRule $rule): ?string
    {
        $days = $rule->intervalDays();
        $kilometers = $rule->intervalKilometers();

        return match ($rule->triggerMode()->value) {
            'date' => null === $days ? null : sprintf('Every %d day%s', $days, 1 === $days ? '' : 's'),
            'odometer' => null === $kilometers ? null : sprintf('Every %d km', $kilometers),
            default => match (true) {
                null !== $days && null !== $kilometers => sprintf('Every %d day%s or %d km', $days, 1 === $days ? '' : 's', $kilometers),
                null !== $days => sprintf('Every %d day%s', $days, 1 === $days ? '' : 's'),
                null !== $kilometers => sprintf('Every %d km', $kilometers),
                default => null,
            },
        };
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';
}
