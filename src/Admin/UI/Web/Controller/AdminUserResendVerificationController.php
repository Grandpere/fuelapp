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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminUserResendVerificationController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly AdminUserManager $userManager,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/ui/admin/users/{id}/resend-verification', name: 'ui_admin_user_resend_verification', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $user = $this->userManager->getUser($id);
        if (!$user instanceof AdminUserRecord) {
            throw new NotFoundHttpException();
        }

        $token = $request->request->get('_token');
        if (!is_scalar($token) || !$this->isCsrfTokenValid('admin_user_resend_verification_'.$id, (string) $token)) {
            throw new NotFoundHttpException();
        }

        $this->auditTrail->record(
            'admin.user.verification_resend_requested.ui',
            'user',
            $user->id,
            [],
            ['channel' => 'admin_ui', 'mailer' => 'not_configured'],
        );

        $this->addFlash('success', sprintf('Verification resend requested for %s (mailer not configured in local).', $user->email));

        return new RedirectResponse($this->generateUrl('ui_admin_user_list'), Response::HTTP_SEE_OTHER);
    }
}
