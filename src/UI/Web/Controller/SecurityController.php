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
    private const SESSION_LOCALE_KEY = 'ui_locale';
    private const AVAILABLE_LOCALES = ['fr', 'en'];

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

    #[Route('/ui/locale/{locale}', name: 'ui_locale_switch', methods: ['GET'])]
    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        if (!in_array($locale, self::AVAILABLE_LOCALES, true)) {
            throw $this->createNotFoundException();
        }

        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_LOCALE_KEY, $locale);
        }

        $target = $this->resolveLocaleRedirectTarget($request);

        return new RedirectResponse($target);
    }

    private function resolveLocaleRedirectTarget(Request $request): string
    {
        $explicitReturnTo = $request->query->get('return_to');
        if (is_string($explicitReturnTo) && '' !== trim($explicitReturnTo)) {
            $validated = $this->validateSameOriginTarget($request, $explicitReturnTo);
            if (null !== $validated) {
                return $validated;
            }
        }

        $referer = $request->headers->get('referer');
        if (is_string($referer) && '' !== trim($referer)) {
            $validated = $this->validateSameOriginTarget($request, $referer);
            if (null !== $validated) {
                return $validated;
            }
        }

        return null !== $this->getUser()
            ? $this->generateUrl('ui_dashboard')
            : $this->generateUrl('ui_login');
    }

    private function validateSameOriginTarget(Request $request, string $target): ?string
    {
        $trimmed = trim($target);
        if ('' === $trimmed) {
            return null;
        }

        if (str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//')) {
            return $trimmed;
        }

        $parts = parse_url($trimmed);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!is_string($scheme) || !is_string($host)) {
            return null;
        }

        if ($scheme !== $request->getScheme() || $host !== $request->getHost()) {
            return null;
        }

        $port = $parts['port'] ?? null;
        if (is_int($port) && $port !== $request->getPort()) {
            return null;
        }

        return $trimmed;
    }
}
