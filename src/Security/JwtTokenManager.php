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

namespace App\Security;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use JsonException;
use RuntimeException;

final readonly class JwtTokenManager
{
    public function __construct(
        private string $jwtSecret,
        private int $jwtTtlSeconds = 3600,
    ) {
    }

    public function create(UserEntity $user): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'sub' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'iat' => $now,
            'exp' => $now + $this->jwtTtlSeconds,
        ];

        return $this->encode($header, $payload);
    }

    /** @return array{sub: string, email: string, iat: int, exp: int} */
    public function parseAndValidate(string $token): array
    {
        $parts = explode('.', $token);
        if (3 !== count($parts)) {
            throw new RuntimeException('Invalid token format.');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        $expected = $this->sign($headerEncoded . '.' . $payloadEncoded);
        if (!hash_equals($expected, $signatureEncoded)) {
            throw new RuntimeException('Invalid token signature.');
        }

        try {
            $payload = json_decode($this->base64UrlDecode($payloadEncoded), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid token payload.', 0, $e);
        }

        if (!is_array($payload)) {
            throw new RuntimeException('Invalid token payload.');
        }

        if (!isset($payload['sub'], $payload['email'], $payload['iat'], $payload['exp'])) {
            throw new RuntimeException('Token payload is missing required claims.');
        }

        if (!is_string($payload['sub']) || !is_string($payload['email']) || !is_int($payload['iat']) || !is_int($payload['exp'])) {
            throw new RuntimeException('Token payload claims have invalid types.');
        }

        if ($payload['exp'] < time()) {
            throw new RuntimeException('Token has expired.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $header
     * @param array<string, mixed> $payload
     */
    private function encode(array $header, array $payload): string
    {
        try {
            $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
            $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to encode token payload.', 0, $e);
        }

        return sprintf('%s.%s.%s', $headerEncoded, $payloadEncoded, $this->sign($headerEncoded . '.' . $payloadEncoded));
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->jwtSecret, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if (false === $decoded) {
            throw new RuntimeException('Invalid base64 data.');
        }

        return $decoded;
    }
}
