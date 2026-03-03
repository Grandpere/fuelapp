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

final class AdminIdentityDeleteController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly AdminIdentityManager $identityManager,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/ui/admin/identities/{id}/delete', name: 'ui_admin_identity_delete', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
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
        if (!is_scalar($token) || !$this->isCsrfTokenValid('admin_identity_delete_'.$id, (string) $token)) {
            throw new NotFoundHttpException();
        }

        $before = [
            'userId' => $identity->userId,
            'userEmail' => $identity->userEmail,
            'provider' => $identity->provider,
            'subject' => $identity->subject,
            'email' => $identity->email,
        ];

        try {
            $this->identityManager->unlinkIdentity($id);
        } catch (LogicException $e) {
            $this->addFlash('error', $e->getMessage());

            return new RedirectResponse($this->generateUrl('ui_admin_identity_list'), Response::HTTP_SEE_OTHER);
        }

        $this->auditTrail->record(
            'admin.identity.unlinked.ui',
            'user_identity',
            $id,
            ['before' => $before],
        );

        $this->addFlash('success', 'Identity deleted.');

        return new RedirectResponse($this->generateUrl('ui_admin_identity_list'), Response::HTTP_SEE_OTHER);
    }
}
