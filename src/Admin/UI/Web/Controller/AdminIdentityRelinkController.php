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
use App\Admin\Application\Identity\AdminIdentityManager;
use App\Admin\Application\Identity\AdminIdentityRecord;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminIdentityRelinkController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly AdminIdentityManager $identityManager,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/ui/admin/identities/{id}/relink', name: 'ui_admin_identity_relink', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $identity = $this->identityManager->getIdentity($id);
        if (!$identity instanceof AdminIdentityRecord) {
            throw new NotFoundHttpException();
        }

        $token = $request->request->get('_token');
        if (!is_scalar($token) || !$this->isCsrfTokenValid('admin_identity_relink_'.$id, (string) $token)) {
            throw new NotFoundHttpException();
        }

        $targetUserId = $request->request->get('user_id');
        if (!is_scalar($targetUserId) || !Uuid::isValid((string) $targetUserId)) {
            $this->addFlash('error', 'Invalid target user id.');

            return new RedirectResponse($this->generateUrl('ui_admin_identity_list'), Response::HTTP_SEE_OTHER);
        }

        $before = $this->snapshot($identity);

        try {
            $updated = $this->identityManager->relinkIdentity($id, (string) $targetUserId);
        } catch (LogicException $e) {
            $this->addFlash('error', $e->getMessage());

            return new RedirectResponse($this->generateUrl('ui_admin_identity_list'), Response::HTTP_SEE_OTHER);
        }

        $after = $this->snapshot($updated);
        $this->auditTrail->record(
            'admin.identity.relinked.ui',
            'user_identity',
            $updated->id,
            [
                'before' => $before,
                'after' => $after,
                'changed' => $this->diff($before, $after),
            ],
        );

        $this->addFlash('success', sprintf('Identity %s has been linked to %s.', $updated->provider, $updated->userEmail));

        return new RedirectResponse($this->generateUrl('ui_admin_identity_list'), Response::HTTP_SEE_OTHER);
    }

    /** @return array<string, mixed> */
    private function snapshot(AdminIdentityRecord $identity): array
    {
        return [
            'userId' => $identity->userId,
            'userEmail' => $identity->userEmail,
            'provider' => $identity->provider,
            'subject' => $identity->subject,
            'email' => $identity->email,
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
