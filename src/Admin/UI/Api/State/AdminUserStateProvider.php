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

namespace App\Admin\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Admin\Application\User\AdminUserManager;
use App\Admin\Application\User\AdminUserRecord;
use App\Admin\UI\Api\Resource\Output\AdminUserOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminUserOutput>
 */
final readonly class AdminUserStateProvider implements ProviderInterface
{
    public function __construct(private AdminUserManager $userManager)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $id = $uriVariables['id'] ?? null;
        if (null !== $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $user = $this->userManager->getUser($id);
            if (null === $user) {
                throw new NotFoundHttpException();
            }

            return $this->toOutput($user);
        }

        $items = $this->userManager->listUsers(
            $this->readFilter($context, 'q'),
            $this->readRoleFilter($context, 'role'),
            $this->readBoolFilter($context, 'isActive'),
        );

        $resources = [];
        foreach ($items as $item) {
            $resources[] = $this->toOutput($item);
        }

        return $resources;
    }

    private function toOutput(AdminUserRecord $user): AdminUserOutput
    {
        return new AdminUserOutput(
            $user->id,
            $user->email,
            $user->roles,
            $user->isActive,
            $user->isAdmin(),
            $user->identityCount,
            $user->isEmailVerified(),
            $user->emailVerifiedAt,
        );
    }

    /** @param array<string, mixed> $context */
    private function readFilter(array $context, string $name): ?string
    {
        $filters = $context['filters'] ?? null;
        if (!is_array($filters)) {
            return null;
        }

        $value = $filters[$name] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    /** @param array<string, mixed> $context */
    private function readRoleFilter(array $context, string $name): ?string
    {
        $value = $this->readFilter($context, $name);

        return in_array($value, ['admin', 'user'], true) ? $value : null;
    }

    /** @param array<string, mixed> $context */
    private function readBoolFilter(array $context, string $name): ?bool
    {
        $value = $this->readFilter($context, $name);
        if (null === $value) {
            return null;
        }

        $lower = mb_strtolower($value);
        if (in_array($lower, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($lower, ['0', 'false', 'no'], true)) {
            return false;
        }

        return null;
    }
}
