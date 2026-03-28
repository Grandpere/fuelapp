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

use App\Admin\Application\Identity\AdminIdentityManager;
use App\Admin\Application\User\AdminUserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminIdentityListController extends AbstractController
{
    public function __construct(
        private readonly AdminIdentityManager $identityManager,
        private readonly AdminUserManager $userManager,
    ) {
    }

    #[Route('/ui/admin/identities', name: 'ui_admin_identity_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $q = $this->readStringFilter($request, 'q');
        $provider = $this->readStringFilter($request, 'provider');
        $userId = $this->readStringFilter($request, 'user_id');

        $identities = [];
        foreach ($this->identityManager->listIdentities($q, $provider, $userId) as $identity) {
            $identities[] = [
                'record' => $identity,
                'userListUrl' => $this->generateUrl('ui_admin_user_list', ['q' => $identity->userEmail]),
                'securityUrl' => $this->generateUrl('ui_admin_security_activity_list', ['actorId' => $identity->userId]),
                'auditUrl' => $this->generateUrl('ui_admin_audit_log_list', ['actorId' => $identity->userId]),
            ];
        }

        return $this->render('admin/identities/index.html.twig', [
            'identities' => $identities,
            'users' => $this->userManager->listUsers(),
            'userOptions' => $this->buildUserOptions(),
            'filters' => [
                'q' => $q ?? '',
                'provider' => $provider ?? '',
                'user_id' => $userId ?? '',
            ],
            'activeFilterSummary' => $this->buildActiveFilterSummary($q, $provider, $userId),
            'supportShortcuts' => $this->buildSupportShortcuts($userId),
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

    /**
     * @return list<array{label:string,value:string}>
     */
    private function buildActiveFilterSummary(?string $q, ?string $provider, ?string $userId): array
    {
        $summary = [];

        if (null !== $q) {
            $summary[] = ['label' => 'Search', 'value' => $q];
        }
        if (null !== $provider) {
            $summary[] = ['label' => 'Provider', 'value' => $provider];
        }
        if (null !== $userId) {
            $user = $this->userManager->getUser($userId);
            $summary[] = ['label' => 'User', 'value' => null !== $user ? sprintf('%s (%s)', $user->email, $userId) : $userId];
        }

        return $summary;
    }

    /**
     * @return list<array{label:string,url:string}>
     */
    private function buildSupportShortcuts(?string $userId): array
    {
        if (null === $userId) {
            return [];
        }

        $user = $this->userManager->getUser($userId);
        if (null === $user) {
            return [];
        }

        return [
            [
                'label' => 'Open user',
                'url' => $this->generateUrl('ui_admin_user_list', ['q' => $user->email]),
            ],
            [
                'label' => 'User security',
                'url' => $this->generateUrl('ui_admin_security_activity_list', ['actorId' => $userId]),
            ],
            [
                'label' => 'User audit',
                'url' => $this->generateUrl('ui_admin_audit_log_list', ['actorId' => $userId]),
            ],
        ];
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    private function buildUserOptions(): array
    {
        $options = [];
        foreach ($this->userManager->listUsers() as $user) {
            $options[] = [
                'id' => $user->id,
                'label' => $user->email,
            ];
        }

        return $options;
    }
}
