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

final class OidcProviderRegistry
{
    /** @var array<string, OidcProvider> */
    private array $providers = [];

    /** @param array<string, mixed> $providersConfig */
    public function __construct(array $providersConfig)
    {
        foreach ($providersConfig as $name => $config) {
            if (!is_array($config)) {
                continue;
            }

            if (!($config['enabled'] ?? false)) {
                continue;
            }

            $issuer = $this->stringOrNull($config['issuer'] ?? null);
            $clientId = $this->stringOrNull($config['client_id'] ?? null);
            $clientSecret = $this->stringOrNull($config['client_secret'] ?? null);
            if (null === $issuer || null === $clientId || null === $clientSecret) {
                continue;
            }

            $label = $this->stringOrNull($config['label'] ?? null) ?? ucfirst($name);
            $scopes = $this->scopes($config['scopes'] ?? ['openid', 'profile', 'email']);

            $this->providers[$name] = new OidcProvider(
                $name,
                $label,
                rtrim($issuer, '/'),
                $clientId,
                $clientSecret,
                $scopes,
            );
        }
    }

    /** @return list<OidcProvider> */
    public function enabledProviders(): array
    {
        return array_values($this->providers);
    }

    public function get(string $name): ?OidcProvider
    {
        return $this->providers[$name] ?? null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    /** @return list<string> */
    private function scopes(mixed $value): array
    {
        if (!is_array($value)) {
            return ['openid', 'profile', 'email'];
        }

        $scopes = [];
        foreach ($value as $scope) {
            if (!is_string($scope) || '' === trim($scope)) {
                continue;
            }

            $scopes[] = trim($scope);
        }

        if ([] === $scopes) {
            return ['openid', 'profile', 'email'];
        }

        return array_values(array_unique($scopes));
    }
}
