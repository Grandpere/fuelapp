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

namespace App\Tests\Unit\Security\Oidc;

use App\Security\Oidc\OidcProviderRegistry;
use PHPUnit\Framework\TestCase;

final class OidcProviderRegistryTest extends TestCase
{
    public function testRegistryKeepsOnlyEnabledAndCompleteProviders(): void
    {
        $registry = new OidcProviderRegistry([
            'auth0' => [
                'enabled' => true,
                'label' => 'Auth0',
                'issuer' => 'https://tenant.auth0.com/',
                'client_id' => 'client',
                'client_secret' => 'secret',
                'scopes' => ['openid', 'email'],
            ],
            'google' => [
                'enabled' => false,
                'label' => 'Google',
                'issuer' => 'https://accounts.google.com',
                'client_id' => 'client',
                'client_secret' => 'secret',
            ],
            'broken' => [
                'enabled' => true,
                'label' => 'Broken',
                'issuer' => '',
                'client_id' => 'client',
                'client_secret' => '',
            ],
        ]);

        $providers = $registry->enabledProviders();
        self::assertCount(1, $providers);
        self::assertSame('auth0', $providers[0]->name);
        self::assertSame('https://tenant.auth0.com', $providers[0]->issuer);
        self::assertSame(['openid', 'email'], $providers[0]->scopes);
        self::assertNull($registry->get('google'));
        self::assertNull($registry->get('broken'));
    }
}
