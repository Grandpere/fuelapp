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

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OidcClient
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function buildAuthorizationUrl(OidcProvider $provider, string $redirectUri, string $state, string $nonce): string
    {
        $discovery = $this->discovery($provider);
        $authorizationEndpoint = $this->requiredString($discovery, 'authorization_endpoint');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $provider->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $provider->scopes),
            'state' => $state,
            'nonce' => $nonce,
        ]);

        return sprintf('%s?%s', $authorizationEndpoint, $query);
    }

    /** @return array{sub: string, email: ?string, name: ?string, picture: ?string} */
    public function exchangeCodeForUserClaims(OidcProvider $provider, string $code, string $redirectUri): array
    {
        $discovery = $this->discovery($provider);
        $tokenEndpoint = $this->requiredString($discovery, 'token_endpoint');
        $userInfoEndpoint = $this->requiredString($discovery, 'userinfo_endpoint');

        $tokenResponse = $this->httpClient->request('POST', $tokenEndpoint, [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $provider->clientId,
                'client_secret' => $provider->clientSecret,
            ],
        ])->toArray(false);
        /** @var array<string, mixed> $tokenResponse */
        $accessToken = $this->requiredString($tokenResponse, 'access_token');

        $claims = $this->httpClient->request('GET', $userInfoEndpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => sprintf('Bearer %s', $accessToken),
            ],
        ])->toArray(false);
        /** @var array<string, mixed> $claims */

        return [
            'sub' => $this->requiredString($claims, 'sub'),
            'email' => $this->optionalString($claims, 'email'),
            'name' => $this->optionalString($claims, 'name'),
            'picture' => $this->optionalString($claims, 'picture'),
        ];
    }

    /** @return array<string, mixed> */
    private function discovery(OidcProvider $provider): array
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf('%s/.well-known/openid-configuration', $provider->issuer),
            ['headers' => ['Accept' => 'application/json']],
        )->toArray(false);

        /** @var array<string, mixed> $response */
        return $response;
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $key): string
    {
        $value = $this->optionalString($payload, $key);
        if (null === $value) {
            throw new RuntimeException(sprintf('OIDC payload missing required key "%s".', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
