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

use App\Admin\Application\Audit\AdminAuditTrail;
use App\Admin\Application\User\AdminUserManager;
use App\Admin\Application\User\AdminUserRecord;
use App\Security\AuthenticatedUser;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminUserToggleActiveController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly AdminUserManager $userManager,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/ui/admin/users/{id}/toggle-active', name: 'ui_admin_user_toggle_active', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $user = $this->userManager->getUser($id);
        if (null === $user) {
            throw new NotFoundHttpException();
        }

        $token = $request->request->get('_token');
        if (!is_scalar($token) || !$this->isCsrfTokenValid('admin_user_toggle_active_'.$id, (string) $token)) {
            throw new NotFoundHttpException();
        }

        $before = $this->snapshot($user);
        $actor = $this->getUser();
        $actorId = $actor instanceof AuthenticatedUser ? $actor->getId()->toRfc4122() : null;

        try {
            $updated = $this->userManager->updateUser($id, !$user->isActive, null, null, $actorId);
        } catch (LogicException $e) {
            $this->addFlash('error', $e->getMessage());

            return new RedirectResponse($this->generateUrl('ui_admin_user_list'), Response::HTTP_SEE_OTHER);
        }

        $after = $this->snapshot($updated);
        $this->auditTrail->record(
            'admin.user.toggled_active.ui',
            'user',
            $updated->id,
            [
                'before' => $before,
                'after' => $after,
                'changed' => $this->diff($before, $after),
            ],
        );

        $this->addFlash('success', sprintf('User %s is now %s.', $updated->email, $updated->isActive ? 'active' : 'inactive'));

        return new RedirectResponse($this->generateUrl('ui_admin_user_list'), Response::HTTP_SEE_OTHER);
    }

    /** @return array<string, mixed> */
    private function snapshot(AdminUserRecord $user): array
    {
        return [
            'email' => $user->email,
            'roles' => $user->roles,
            'isActive' => $user->isActive,
            'isEmailVerified' => $user->isEmailVerified(),
        ];
    }

    /** @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;
            if ($previous !== $value) {
                $changed[$key] = ['before' => $previous, 'after' => $value];
            }
        }

        return $changed;
    }
}
