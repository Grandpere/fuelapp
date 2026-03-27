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
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminMaintenanceReminderListController extends AbstractController
{
    public function __construct(
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
        $dueFrom = $this->readDateFilter($request, 'due_from');
        $dueTo = $this->readDateFilter($request, 'due_to');

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

        $ruleNames = [];
        foreach ($this->ruleRepository->allForSystem() as $rule) {
            $ruleNames[$rule->id()->toString()] = $rule->name();
        }

        return $this->render('admin/maintenance/reminders/index.html.twig', [
            'reminders' => $reminders,
            'ruleNames' => $ruleNames,
            'metrics' => $metrics,
            'filters' => [
                'ownerId' => $ownerId,
                'vehicleId' => $vehicleId,
                'dueBy' => $dueBy,
                'dueFrom' => $dueFrom?->format('Y-m-d'),
                'dueTo' => $dueTo?->format('Y-m-d'),
            ],
        ]);
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
}
