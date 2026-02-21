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

namespace App\Admin\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Admin\UI\Api\Resource\Output\AdminMaintenanceReminderOutput;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Domain\MaintenanceReminder;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminMaintenanceReminderOutput>
 */
final readonly class AdminMaintenanceReminderStateProvider implements ProviderInterface
{
    public function __construct(private MaintenanceReminderRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $id = $uriVariables['id'] ?? null;

        $ownerId = $this->readUuidFilter($context, 'ownerId');
        $vehicleId = $this->readUuidFilter($context, 'vehicleId');
        $dueBy = $this->readDueByFilter($context, 'dueBy');
        $dueFrom = $this->readDateFilter($context, 'dueFrom');
        $dueTo = $this->readDateFilter($context, 'dueTo');

        $resources = [];
        foreach ($this->repository->allForSystem() as $reminder) {
            if (is_string($id) && Uuid::isValid($id) && $reminder->id()->toString() !== $id) {
                continue;
            }

            if (null !== $ownerId && $reminder->ownerId() !== $ownerId) {
                continue;
            }
            if (null !== $vehicleId && $reminder->vehicleId() !== $vehicleId) {
                continue;
            }
            if (null !== $dueBy && !$this->matchesDueBy($reminder, $dueBy)) {
                continue;
            }
            if (!$this->matchesDateWindow($reminder, $dueFrom, $dueTo)) {
                continue;
            }

            $resources[] = $this->toOutput($reminder);
        }

        if (null !== $id) {
            if (!is_string($id) || !Uuid::isValid($id) || [] === $resources) {
                throw new NotFoundHttpException();
            }

            return $resources[0];
        }

        return $resources;
    }

    private function toOutput(MaintenanceReminder $reminder): AdminMaintenanceReminderOutput
    {
        return new AdminMaintenanceReminderOutput(
            $reminder->id()->toString(),
            $reminder->ownerId(),
            $reminder->vehicleId(),
            $reminder->ruleId(),
            $reminder->dueAtDate(),
            $reminder->dueAtOdometerKilometers(),
            $reminder->dueByDate(),
            $reminder->dueByOdometer(),
            $reminder->createdAt(),
        );
    }

    private function matchesDueBy(MaintenanceReminder $reminder, string $dueBy): bool
    {
        return match ($dueBy) {
            'date' => $reminder->dueByDate(),
            'odometer' => $reminder->dueByOdometer(),
            'both' => $reminder->dueByDate() && $reminder->dueByOdometer(),
            default => true,
        };
    }

    private function matchesDateWindow(MaintenanceReminder $reminder, ?DateTimeImmutable $dueFrom, ?DateTimeImmutable $dueTo): bool
    {
        $dueAt = $reminder->dueAtDate();
        if (null !== $dueFrom) {
            if (null === $dueAt || $dueAt < $dueFrom->setTime(0, 0, 0)) {
                return false;
            }
        }

        if (null !== $dueTo) {
            if (null === $dueAt || $dueAt > $dueTo->setTime(23, 59, 59)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $context */
    private function readUuidFilter(array $context, string $name): ?string
    {
        $value = $this->readFilter($context, $name);
        if (null === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $context */
    private function readDateFilter(array $context, string $name): ?DateTimeImmutable
    {
        $value = $this->readFilter($context, $name);
        if (null === $value) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false !== $parsed) {
            return $parsed;
        }

        $date = date_create_immutable($value);

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    /** @param array<string, mixed> $context */
    private function readDueByFilter(array $context, string $name): ?string
    {
        $value = $this->readFilter($context, $name);

        return in_array($value, ['date', 'odometer', 'both'], true) ? $value : null;
    }

    /** @param array<string, mixed> $context */
    private function readFilter(array $context, string $name): ?string
    {
        $filters = $context['filters'] ?? null;
        if (!is_array($filters)) {
            return null;
        }

        $value = $filters[$name] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
