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

use App\Admin\Application\User\AdminUserManager;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use ValueError;

final class AdminMaintenanceReminderListController extends AbstractController
{
    public function __construct(
        private readonly AdminUserManager $userManager,
        private readonly MaintenanceReminderRepository $reminderRepository,
        private readonly MaintenanceReminderRuleRepository $ruleRepository,
        private readonly VehicleRepository $vehicleRepository,
    ) {
    }

    #[Route('/ui/admin/maintenance/reminders', name: 'ui_admin_maintenance_reminder_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $ownerId = $this->readUuidFilter($request, 'owner_id');
        $vehicleId = $this->readUuidFilter($request, 'vehicle_id');
        $dueBy = $this->readDueByFilter($request, 'due_by');
        $eventType = $this->readEventTypeFilter($request, 'event_type');
        $dueFrom = $this->readDateFilter($request, 'due_from');
        $dueTo = $this->readDateFilter($request, 'due_to');

        $ruleSummaries = [];
        foreach ($this->ruleRepository->allForSystem() as $rule) {
            $ruleSummaries[$rule->id()->toString()] = [
                'name' => $rule->name(),
                'eventType' => $rule->eventType()?->value,
            ];
        }

        $reminders = [];
        $metrics = [
            'date' => 0,
            'odometer' => 0,
            'both' => 0,
        ];
        foreach ($this->reminderRepository->allForSystem() as $reminder) {
            if (null !== $ownerId && $reminder->ownerId() !== $ownerId) {
                continue;
            }
            if (null !== $vehicleId && $reminder->vehicleId() !== $vehicleId) {
                continue;
            }
            if (null !== $dueBy && !$this->matchesDueBy($reminder->dueByDate(), $reminder->dueByOdometer(), $dueBy)) {
                continue;
            }
            $ruleSummary = $ruleSummaries[$reminder->ruleId()] ?? null;
            if (null !== $eventType && ($ruleSummary['eventType'] ?? null) !== $eventType->value) {
                continue;
            }
            if (!$this->matchesDateWindow($reminder->dueAtDate(), $dueFrom, $dueTo)) {
                continue;
            }

            $triggerLabel = $this->buildTriggerLabel($reminder->dueByDate(), $reminder->dueByOdometer());
            ++$metrics[$triggerLabel];

            $vehicle = $this->vehicleRepository->get($reminder->vehicleId());
            $reminders[] = [
                'reminder' => $reminder,
                'vehicle' => $vehicle,
                'triggerLabel' => $triggerLabel,
            ];
        }

        return $this->render('admin/maintenance/reminders/index.html.twig', [
            'reminders' => $reminders,
            'ruleSummaries' => $ruleSummaries,
            'metrics' => $metrics,
            'contextVehicle' => null !== $vehicleId ? $this->vehicleRepository->get($vehicleId) : null,
            'ownerOptions' => $this->buildOwnerOptions(),
            'vehicleOptions' => $this->buildVehicleOptions(),
            'filters' => [
                'ownerId' => $ownerId,
                'vehicleId' => $vehicleId,
                'dueBy' => $dueBy,
                'eventType' => $eventType?->value,
                'dueFrom' => $dueFrom?->format('Y-m-d'),
                'dueTo' => $dueTo?->format('Y-m-d'),
            ],
            'activeFilterSummary' => $this->buildActiveFilterSummary($ownerId, $vehicleId, $dueBy, $eventType, $dueFrom, $dueTo),
            'eventTypeOptions' => array_map(static fn (MaintenanceEventType $type): string => $type->value, MaintenanceEventType::cases()),
        ]);
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function buildActiveFilterSummary(?string $ownerId, ?string $vehicleId, ?string $dueBy, ?MaintenanceEventType $eventType, ?DateTimeImmutable $dueFrom, ?DateTimeImmutable $dueTo): array
    {
        $summary = [];

        if (null !== $ownerId) {
            $user = $this->userManager->getUser($ownerId);
            $summary[] = ['label' => 'Owner', 'value' => null !== $user ? sprintf('%s (%s)', $user->email, $ownerId) : $ownerId];
        }

        if (null !== $vehicleId) {
            $vehicle = $this->vehicleRepository->get($vehicleId);
            $summary[] = [
                'label' => 'Vehicle',
                'value' => null !== $vehicle ? sprintf('%s (%s)', $vehicle->name(), $vehicleId) : $vehicleId,
            ];
        }

        if (null !== $dueBy) {
            $summary[] = ['label' => 'Due by', 'value' => $dueBy];
        }

        if (null !== $eventType) {
            $summary[] = ['label' => 'Event type', 'value' => $eventType->value];
        }

        if (null !== $dueFrom) {
            $summary[] = ['label' => 'Due from', 'value' => $dueFrom->format('Y-m-d')];
        }

        if (null !== $dueTo) {
            $summary[] = ['label' => 'Due to', 'value' => $dueTo->format('Y-m-d')];
        }

        return $summary;
    }

    private function buildTriggerLabel(bool $dueByDate, bool $dueByOdometer): string
    {
        return match (true) {
            $dueByDate && $dueByOdometer => 'both',
            $dueByDate => 'date',
            $dueByOdometer => 'odometer',
            default => 'date',
        };
    }

    private function matchesDueBy(bool $dueByDate, bool $dueByOdometer, string $dueBy): bool
    {
        return match ($dueBy) {
            'date' => $dueByDate,
            'odometer' => $dueByOdometer,
            'both' => $dueByDate && $dueByOdometer,
            default => true,
        };
    }

    private function matchesDateWindow(?DateTimeImmutable $dueAtDate, ?DateTimeImmutable $dueFrom, ?DateTimeImmutable $dueTo): bool
    {
        if (null !== $dueFrom) {
            if (null === $dueAtDate || $dueAtDate < $dueFrom->setTime(0, 0, 0)) {
                return false;
            }
        }

        if (null !== $dueTo) {
            if (null === $dueAtDate || $dueAtDate > $dueTo->setTime(23, 59, 59)) {
                return false;
            }
        }

        return true;
    }

    private function readUuidFilter(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ('' === $trimmed || !Uuid::isValid($trimmed)) {
            return null;
        }

        return $trimmed;
    }

    private function readDueByFilter(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return in_array($trimmed, ['date', 'odometer', 'both'], true) ? $trimmed : null;
    }

    private function readEventTypeFilter(Request $request, string $name): ?MaintenanceEventType
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ('' === $trimmed) {
            return null;
        }

        try {
            return MaintenanceEventType::from($trimmed);
        } catch (ValueError) {
            return null;
        }
    }

    private function readDateFilter(Request $request, string $name): ?DateTimeImmutable
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ('' === $trimmed) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);

        return false === $parsed ? null : $parsed;
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    private function buildOwnerOptions(): array
    {
        $options = [];
        foreach ($this->userManager->listUsers() as $user) {
            $options[] = [
                'id' => $user->id,
                'label' => $user->email,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    private function buildVehicleOptions(): array
    {
        $options = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            $label = $vehicle->name();
            $plateNumber = trim($vehicle->plateNumber());
            if ('' !== $plateNumber) {
                $label .= sprintf(' (%s)', $plateNumber);
            }

            $options[] = [
                'id' => $vehicle->id()->toString(),
                'label' => $label,
            ];
        }

        usort($options, static fn (array $left, array $right): int => $left['label'] <=> $right['label']);

        return $options;
    }
}
