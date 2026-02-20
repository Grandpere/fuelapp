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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OidcStartController extends AbstractController
{
    private const PROVIDER_ROUTE_REQUIREMENT = '[a-z0-9_-]+';

    public function __construct(
        private readonly OidcProviderRegistry $providerRegistry,
        private readonly OidcClient $oidcClient,
    ) {
    }

    #[Route('/ui/login/oidc/{provider}', name: 'ui_oidc_start', methods: ['GET'], requirements: ['provider' => self::PROVIDER_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $provider): RedirectResponse
    {
        $config = $this->providerRegistry->get($provider);
        if (null === $config) {
            $this->addFlash('error', 'Unknown OIDC provider.');

            return $this->redirectToRoute('ui_login');
        }

        $session = $request->getSession();
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $session->set(sprintf('oidc_state_%s', $provider), $state);
        $session->set(sprintf('oidc_nonce_%s', $provider), $nonce);

        $authorizationUrl = $this->oidcClient->buildAuthorizationUrl(
            $config,
            $this->generateUrl('ui_oidc_callback', ['provider' => $provider], UrlGeneratorInterface::ABSOLUTE_URL),
            $state,
            $nonce,
        );

        return new RedirectResponse($authorizationUrl);
    }
}
