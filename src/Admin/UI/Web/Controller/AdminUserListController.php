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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminUserListController extends AbstractController
{
    public function __construct(private readonly AdminUserManager $userManager)
    {
    }

    #[Route('/ui/admin/users', name: 'ui_admin_user_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $q = $this->readFilter($request, 'q');
        $role = $this->readRoleFilter($request, 'role');
        $isActive = $this->readBoolFilter($request, 'is_active');
        $verification = $this->readVerificationFilter($request, 'verification');
        $hasIdentity = $this->readBoolFilter($request, 'has_identity');

        $userRows = [];
        $metrics = [
            'inactive' => 0,
            'unverified' => 0,
            'withoutIdentity' => 0,
            'admins' => 0,
        ];
        $users = [];
        foreach ($this->userManager->listUsers($q, $role, $isActive) as $user) {
            $row = [
                'id' => $user->id,
                'email' => $user->email,
                'roles' => $user->roles,
                'isActive' => $user->isActive,
                'isAdmin' => $user->isAdmin(),
                'identityCount' => $user->identityCount,
                'isEmailVerified' => $user->isEmailVerified(),
                'identitiesUrl' => $this->generateUrl('ui_admin_identity_list', ['user_id' => $user->id]),
                'securityUrl' => $this->generateUrl('ui_admin_security_activity_list', ['actorId' => $user->id]),
                'auditUrl' => $this->generateUrl('ui_admin_audit_log_list', ['actorId' => $user->id]),
            ];

            if (!$row['isActive']) {
                ++$metrics['inactive'];
            }
            if (!$row['isEmailVerified']) {
                ++$metrics['unverified'];
            }
            if (0 === $row['identityCount']) {
                ++$metrics['withoutIdentity'];
            }
            if ($row['isAdmin']) {
                ++$metrics['admins'];
            }

            $userRows[] = $row;
        }

        foreach ($userRows as $row) {
            if (null !== $verification && $row['isEmailVerified'] !== $verification) {
                continue;
            }
            if (null !== $hasIdentity && (($row['identityCount'] > 0) !== $hasIdentity)) {
                continue;
            }

            $users[] = $row;
        }

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'metrics' => $metrics,
            'filters' => [
                'q' => $q ?? '',
                'role' => $role ?? '',
                'is_active' => null === $isActive ? '' : ($isActive ? '1' : '0'),
                'verification' => null === $verification ? '' : ($verification ? 'verified' : 'unverified'),
                'has_identity' => null === $hasIdentity ? '' : ($hasIdentity ? '1' : '0'),
            ],
            'activeFilterSummary' => $this->buildActiveFilterSummary($q, $role, $isActive, $verification, $hasIdentity),
        ]);
    }

    private function readFilter(Request $request, string $name): ?string
    {
        $raw = $request->query->get($name);
        if (!is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return '' === $value ? null : $value;
    }

    private function readRoleFilter(Request $request, string $name): ?string
    {
        $value = $this->readFilter($request, $name);

        return in_array($value, ['admin', 'user'], true) ? $value : null;
    }

    private function readBoolFilter(Request $request, string $name): ?bool
    {
        $value = $this->readFilter($request, $name);
        if (null === $value) {
            return null;
        }

        if (in_array($value, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no'], true)) {
            return false;
        }

        return null;
    }

    private function readVerificationFilter(Request $request, string $name): ?bool
    {
        $value = $this->readFilter($request, $name);

        return match ($value) {
            'verified' => true,
            'unverified' => false,
            default => null,
        };
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function buildActiveFilterSummary(?string $q, ?string $role, ?bool $isActive, ?bool $verification, ?bool $hasIdentity): array
    {
        $summary = [];

        if (null !== $q) {
            $summary[] = ['label' => 'Search', 'value' => $q];
        }
        if (null !== $role) {
            $summary[] = ['label' => 'Role', 'value' => $role];
        }
        if (null !== $isActive) {
            $summary[] = ['label' => 'Status', 'value' => $isActive ? 'active' : 'inactive'];
        }
        if (null !== $verification) {
            $summary[] = ['label' => 'Verification', 'value' => $verification ? 'verified' : 'unverified'];
        }
        if (null !== $hasIdentity) {
            $summary[] = ['label' => 'Identities', 'value' => $hasIdentity ? 'linked' : 'missing'];
        }

        return $summary;
    }
}
