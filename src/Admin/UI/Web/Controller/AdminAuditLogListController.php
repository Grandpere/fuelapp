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
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminAuditLogListController extends AbstractController
{
    public function __construct(private readonly AdminAuditLogReader $reader)
    {
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
            $entries[] = $entry;
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
}
