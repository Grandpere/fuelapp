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

        $users = [];
        foreach ($this->userManager->listUsers($q, $role, $isActive) as $user) {
            $users[] = [
                'id' => $user->id,
                'email' => $user->email,
                'roles' => $user->roles,
                'isActive' => $user->isActive,
                'isAdmin' => $user->isAdmin(),
                'identityCount' => $user->identityCount,
            ];
        }

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'filters' => [
                'q' => $q ?? '',
                'role' => $role ?? '',
                'is_active' => null === $isActive ? '' : ($isActive ? '1' : '0'),
            ],
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
}
