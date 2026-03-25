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

namespace App\UI\Web\Controller;

use App\Security\Oidc\OidcProviderRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly OidcProviderRegistry $oidcProviderRegistry,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/login', name: 'ui_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('ui_receipt_index');
        }

        return $this->render('security/login.html.twig', [
            'lastUsername' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'oidcProviders' => $this->oidcProviderRegistry->enabledProviders(),
        ]);
    }

    #[Route('/ui/logout', name: 'ui_logout', methods: ['POST'])]
    public function logout(Request $request): RedirectResponse
    {
        $submittedToken = $request->request->getString('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('logout', $submittedToken))) {
            throw $this->createAccessDeniedException('Invalid logout CSRF token.');
        }

        $this->tokenStorage->setToken(null);
        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->invalidate();
        }

        return $this->redirectToRoute('ui_login');
    }
}
