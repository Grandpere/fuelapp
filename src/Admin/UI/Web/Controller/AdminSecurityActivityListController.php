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

use App\Admin\Application\Security\SecurityActivityReader;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminSecurityActivityListController extends AbstractController
{
    public function __construct(private readonly SecurityActivityReader $reader)
    {
    }

    #[Route('/ui/admin/security-activities', name: 'ui_admin_security_activity_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $action = $this->readStringFilter($request, 'action');
        $actorId = $this->readStringFilter($request, 'actorId');
        $actorId = (is_string($actorId) && Uuid::isValid($actorId)) ? $actorId : null;
        $from = $this->readDateFilter($request, 'from');
        $to = $this->readDateFilter($request, 'to');

        $entries = [];
        foreach ($this->reader->search($action, $actorId, $from, $to) as $entry) {
            $entries[] = $entry;
        }

        return $this->render('admin/security/index.html.twig', [
            'entries' => $entries,
            'filters' => [
                'action' => $action ?? '',
                'actorId' => $actorId ?? '',
                'from' => $from?->format('Y-m-d') ?? '',
                'to' => $to?->format('Y-m-d') ?? '',
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

        return false === $date ? null : $date;
    }
}
