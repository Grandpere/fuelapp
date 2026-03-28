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

use App\Admin\Application\Audit\AdminAuditLogReader;
use App\Admin\Application\User\AdminUserManager;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminAuditLogListController extends AbstractController
{
    public function __construct(
        private readonly AdminAuditLogReader $reader,
        private readonly AdminUserManager $userManager,
    ) {
    }

    #[Route('/ui/admin/audit-logs', name: 'ui_admin_audit_log_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $action = $this->readStringFilter($request, 'action');
        $actorId = $this->readStringFilter($request, 'actorId');
        $targetType = $this->readStringFilter($request, 'targetType');
        $targetId = $this->readStringFilter($request, 'targetId');
        $correlationId = $this->readStringFilter($request, 'correlationId');
        $from = $this->readDateFilter($request, 'from');
        $to = $this->readDateFilter($request, 'to');

        $entries = [];
        foreach ($this->reader->search($action, $actorId, $targetType, $targetId, $correlationId, $from, $to) as $entry) {
            $entries[] = [
                'entry' => $entry,
                'userUrl' => null !== $entry->actorId ? $this->generateUrl('ui_admin_user_list', ['q' => $entry->actorEmail ?? $entry->actorId]) : null,
                'securityUrl' => null !== $entry->actorId ? $this->generateUrl('ui_admin_security_activity_list', array_filter([
                    'actorId' => $entry->actorId,
                    'action' => str_starts_with($entry->action, 'security.') ? $entry->action : null,
                ])) : null,
                'correlationUrl' => '' !== trim($entry->correlationId) ? $this->generateUrl('ui_admin_audit_log_list', ['correlationId' => $entry->correlationId]) : null,
            ];
        }

        return $this->render('admin/audit/index.html.twig', [
            'entries' => $entries,
            'filters' => [
                'action' => $action,
                'actorId' => $actorId,
                'targetType' => $targetType,
                'targetId' => $targetId,
                'correlationId' => $correlationId,
                'from' => $from?->format('Y-m-d'),
                'to' => $to?->format('Y-m-d'),
            ],
            'activeFilterSummary' => $this->buildActiveFilterSummary($action, $actorId, $targetType, $targetId, $correlationId, $from, $to),
            'supportShortcuts' => $this->buildSupportShortcuts($actorId, $correlationId),
        ]);
    }

    private function readStringFilter(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function readDateFilter(Request $request, string $name): ?DateTimeImmutable
    {
        $value = $this->readStringFilter($request, $name);
        if (null === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false === $date) {
            return null;
        }

        return $date;
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function buildActiveFilterSummary(?string $action, ?string $actorId, ?string $targetType, ?string $targetId, ?string $correlationId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $summary = [];

        foreach ([
            ['label' => 'Action', 'value' => $action],
            ['label' => 'Actor', 'value' => $actorId],
            ['label' => 'Target type', 'value' => $targetType],
            ['label' => 'Target id', 'value' => $targetId],
            ['label' => 'Correlation', 'value' => $correlationId],
        ] as $item) {
            if (is_string($item['value']) && '' !== $item['value']) {
                $summary[] = $item;
            }
        }

        if ($from instanceof DateTimeImmutable) {
            $summary[] = ['label' => 'From', 'value' => $from->format('Y-m-d')];
        }
        if ($to instanceof DateTimeImmutable) {
            $summary[] = ['label' => 'To', 'value' => $to->format('Y-m-d')];
        }

        return $summary;
    }

    /**
     * @return list<array{label:string,url:string}>
     */
    private function buildSupportShortcuts(?string $actorId, ?string $correlationId): array
    {
        $shortcuts = [];

        if (null !== $actorId) {
            $user = $this->userManager->getUser($actorId);
            if (null !== $user) {
                $shortcuts[] = [
                    'label' => 'Open user',
                    'url' => $this->generateUrl('ui_admin_user_list', ['q' => $user->email]),
                ];
                $shortcuts[] = [
                    'label' => 'User identities',
                    'url' => $this->generateUrl('ui_admin_identity_list', ['user_id' => $actorId]),
                ];
                $shortcuts[] = [
                    'label' => 'User security',
                    'url' => $this->generateUrl('ui_admin_security_activity_list', ['actorId' => $actorId]),
                ];
            }
        }

        if (null !== $correlationId) {
            $shortcuts[] = [
                'label' => 'Same correlation',
                'url' => $this->generateUrl('ui_admin_audit_log_list', ['correlationId' => $correlationId]),
            ];
        }

        return $shortcuts;
    }
}
