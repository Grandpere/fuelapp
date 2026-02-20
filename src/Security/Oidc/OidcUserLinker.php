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

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserIdentityEntity;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final readonly class OidcUserLinker
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function resolveUser(string $provider, string $subject, ?string $email): UserEntity
    {
        /** @var UserIdentityEntity|null $identity */
        $identity = $this->em->getRepository(UserIdentityEntity::class)->findOneBy([
            'provider' => $provider,
            'subject' => $subject,
        ]);

        if (null !== $identity) {
            return $identity->getUser();
        }

        $normalizedEmail = $this->normalizeEmail($email);
        if (null === $normalizedEmail) {
            throw new RuntimeException('OIDC account is not linkable: missing email claim.');
        }

        /** @var UserEntity|null $user */
        $user = $this->em->getRepository(UserEntity::class)->findOneBy(['email' => $normalizedEmail]);
        if (null === $user) {
            $user = new UserEntity();
            $user->setId(Uuid::v7());
            $user->setEmail($normalizedEmail);
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $this->em->persist($user);
        }

        $newIdentity = new UserIdentityEntity();
        $newIdentity->setId(Uuid::v7());
        $newIdentity->setUser($user);
        $newIdentity->setProvider($provider);
        $newIdentity->setSubject($subject);
        $newIdentity->setEmail($normalizedEmail);
        $this->em->persist($newIdentity);
        $this->em->flush();

        return $user;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (!is_string($email)) {
            return null;
        }

        $normalized = mb_strtolower(trim($email));

        return '' === $normalized ? null : $normalized;
    }
}
