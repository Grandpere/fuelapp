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

        $ruleName = $reminder->ruleId();
        $rule = $this->ruleRepository->get($reminder->ruleId());
        if (null !== $rule) {
            $ruleName = $rule->name();
        }

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
        ]);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';
}
