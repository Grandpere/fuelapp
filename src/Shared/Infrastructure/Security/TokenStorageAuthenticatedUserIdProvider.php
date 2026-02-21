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

namespace App\Shared\Infrastructure\Security;

use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class TokenStorageAuthenticatedUserIdProvider implements AuthenticatedUserIdProvider
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function getAuthenticatedUserId(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof UserEntity) {
            return null;
        }

        return $user->getId()->toRfc4122();
    }
}
