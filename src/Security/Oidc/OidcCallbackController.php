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

namespace App\Security\Oidc;

use App\Security\LoginFormAuthenticator;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OidcCallbackController extends AbstractController
{
    private const PROVIDER_ROUTE_REQUIREMENT = '[a-z0-9_-]+';

    public function __construct(
        private readonly OidcProviderRegistry $providerRegistry,
        private readonly OidcClient $oidcClient,
        private readonly OidcUserLinker $oidcUserLinker,
        private readonly Security $security,
    ) {
    }

    #[Route('/ui/login/oidc/{provider}/callback', name: 'ui_oidc_callback', methods: ['GET'], requirements: ['provider' => self::PROVIDER_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $provider): RedirectResponse
    {
        $config = $this->providerRegistry->get($provider);
        if (null === $config) {
            $this->addFlash('error', 'Unknown OIDC provider.');

            return $this->redirectToRoute('ui_login');
        }

        $code = $request->query->getString('code');
        $state = $request->query->getString('state');
        if ('' === $code || '' === $state) {
            $this->addFlash('error', 'OIDC callback is missing required parameters.');

            return $this->redirectToRoute('ui_login');
        }

        $session = $request->getSession();
        $expectedState = $session->get(sprintf('oidc_state_%s', $provider));
        $session->remove(sprintf('oidc_state_%s', $provider));
        $session->remove(sprintf('oidc_nonce_%s', $provider));

        if (!is_string($expectedState) || '' === $expectedState || !hash_equals($expectedState, $state)) {
            $this->addFlash('error', 'OIDC state validation failed.');

            return $this->redirectToRoute('ui_login');
        }

        try {
            $claims = $this->oidcClient->exchangeCodeForUserClaims(
                $config,
                $code,
                $this->generateUrl('ui_oidc_callback', ['provider' => $provider], UrlGeneratorInterface::ABSOLUTE_URL),
            );

            $user = $this->oidcUserLinker->resolveUser(
                $config->name,
                $claims['sub'],
                $claims['email'],
            );
        } catch (RuntimeException) {
            $this->addFlash('error', 'OIDC login failed. Please contact support if the issue persists.');

            return $this->redirectToRoute('ui_login');
        }

        $this->security->login($user, LoginFormAuthenticator::class, 'main');

        return $this->redirectToRoute('ui_receipt_index');
    }
}
