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

        return $this->render('admin/identities/index.html.twig', [
            'identities' => $this->identityManager->listIdentities($q, $provider, $userId),
            'users' => $this->userManager->listUsers(),
            'filters' => [
                'q' => $q ?? '',
                'provider' => $provider ?? '',
                'user_id' => $userId ?? '',
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
}
