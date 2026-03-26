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
use App\Maintenance\Domain\MaintenanceReminderRule;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class MaintenanceReminderRuleDeleteController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly MaintenanceReminderRuleRepository $ruleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    #[Route('/ui/maintenance/rules/{id}/delete', name: 'ui_maintenance_rule_delete', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $rule = $this->ruleRepository->get($id);
        if (!$rule instanceof MaintenanceReminderRule || $rule->ownerId() !== $ownerId) {
            throw new NotFoundHttpException();
        }

        $token = $request->request->get('_token');
        if (!is_scalar($token) || !$this->isCsrfTokenValid('maintenance_rule_delete_'.$id, (string) $token)) {
            throw new NotFoundHttpException();
        }

        $this->ruleRepository->delete($id);
        $this->addFlash('success', 'Reminder rule deleted.');

        return new RedirectResponse($this->generateUrl('ui_maintenance_index', ['vehicle_id' => $rule->vehicleId()]), Response::HTTP_SEE_OTHER);
    }
}
