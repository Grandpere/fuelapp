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
use Stringable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminUserListController extends AbstractController
{
    public function __construct(
        private readonly AdminUserManager $userManager,
        private readonly TranslatorInterface $translator,
    ) {
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
            $row['signal'] = $this->buildSignal($row);
            $row['severity'] = $this->buildSeverityScore($row);

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

        usort(
            $users,
            static fn (array $left, array $right): int => $right['severity'] <=> $left['severity'],
        );

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'metrics' => $metrics,
            'supportShortcuts' => $this->buildSupportShortcuts($users),
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

    /**
     * @param array{
     *   email:string,
     *   isActive:bool,
     *   isAdmin:bool,
     *   identityCount:int,
     *   isEmailVerified:bool
     * } $row
     *
     * @return array{headline:string,detail:string}
     */
    private function buildSignal(array $row): array
    {
        if (!$row['isActive'] && !$row['isEmailVerified']) {
            return [
                'headline' => $this->t('admin.users.signal.inactive_and_unverified.headline'),
                'detail' => $this->t('admin.users.signal.inactive_and_unverified.detail'),
            ];
        }

        if (0 === $row['identityCount']) {
            return [
                'headline' => $this->t('admin.users.signal.missing_identities.headline'),
                'detail' => $this->t('admin.users.signal.missing_identities.detail'),
            ];
        }

        if (!$row['isEmailVerified']) {
            return [
                'headline' => $this->t('admin.users.signal.email_not_verified.headline'),
                'detail' => $this->t('admin.users.signal.email_not_verified.detail'),
            ];
        }

        if (!$row['isActive']) {
            return [
                'headline' => $this->t('admin.users.signal.inactive_account.headline'),
                'detail' => $this->t('admin.users.signal.inactive_account.detail'),
            ];
        }

        if ($row['isAdmin']) {
            return [
                'headline' => $this->t('admin.users.signal.admin_account.headline'),
                'detail' => $this->t('admin.users.signal.admin_account.detail'),
            ];
        }

        return [
            'headline' => $this->t('admin.users.signal.healthy_account.headline'),
            'detail' => $this->t('admin.users.signal.healthy_account.detail'),
        ];
    }

    /**
     * @param array{
     *   isActive:bool,
     *   isAdmin:bool,
     *   identityCount:int,
     *   isEmailVerified:bool
     * } $row
     */
    private function buildSeverityScore(array $row): int
    {
        $score = 0;

        if (0 === $row['identityCount']) {
            $score += 40;
        }
        if (!$row['isEmailVerified']) {
            $score += 30;
        }
        if (!$row['isActive']) {
            $score += 20;
        }
        if ($row['isAdmin']) {
            $score += 5;
        }

        return $score;
    }

    /**
     * @param list<array{
     *   email:string,
     *   identitiesUrl:string,
     *   securityUrl:string,
     *   auditUrl:string,
     *   isActive:bool,
     *   identityCount:int,
     *   isEmailVerified:bool
     * }> $users
     *
     * @return list<array{label:string,url:string}>
     */
    private function buildSupportShortcuts(array $users): array
    {
        $shortcuts = [];

        foreach ($users as $user) {
            if (0 === $user['identityCount']) {
                $shortcuts[] = ['label' => $this->t('admin.users.shortcuts.open_next_missing_identity'), 'url' => $user['identitiesUrl']];
                break;
            }
        }

        foreach ($users as $user) {
            if (!$user['isEmailVerified']) {
                $shortcuts[] = ['label' => $this->t('admin.users.shortcuts.open_next_unverified_account'), 'url' => $user['auditUrl']];
                break;
            }
        }

        foreach ($users as $user) {
            if (!$user['isActive']) {
                $shortcuts[] = ['label' => $this->t('admin.users.shortcuts.open_next_inactive_account'), 'url' => $user['securityUrl']];
                break;
            }
        }

        return $shortcuts;
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
            $summary[] = ['label' => $this->t('admin.users.filter_summary.search'), 'value' => $q];
        }
        if (null !== $role) {
            $summary[] = [
                'label' => $this->t('admin.users.filter_summary.role'),
                'value' => $this->t('admin.users.filters.role_option_'.$role),
            ];
        }
        if (null !== $isActive) {
            $summary[] = [
                'label' => $this->t('admin.users.filter_summary.status'),
                'value' => $this->t($isActive ? 'admin.users.status.active' : 'admin.users.status.inactive'),
            ];
        }
        if (null !== $verification) {
            $summary[] = [
                'label' => $this->t('admin.users.filter_summary.verification'),
                'value' => $this->t($verification ? 'admin.users.verification.verified' : 'admin.users.verification.unverified'),
            ];
        }
        if (null !== $hasIdentity) {
            $summary[] = [
                'label' => $this->t('admin.users.filter_summary.identities'),
                'value' => $this->t($hasIdentity ? 'admin.users.identities.linked' : 'admin.users.identities.missing'),
            ];
        }

        return $summary;
    }

    /**
     * @param array<string, bool|float|int|string|Stringable|null> $parameters
     */
    private function t(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }
}
