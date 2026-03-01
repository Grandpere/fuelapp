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
use ApiPlatform\State\ProcessorInterface;
use App\Admin\Application\Audit\AdminAuditTrail;
use App\Admin\Application\User\AdminUserManager;
use App\Admin\Application\User\AdminUserRecord;
use App\Admin\UI\Api\Resource\Input\AdminUserUpdateInput;
use App\Admin\UI\Api\Resource\Output\AdminUserOutput;
use App\Security\AuthenticatedUser;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminUserOutput>
 */
final readonly class AdminUserStateProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminUserManager $userManager,
        private Security $security,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminUserOutput
    {
        if (!$data instanceof AdminUserUpdateInput) {
            throw new InvalidArgumentException('Invalid user input.');
        }

        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $user = $this->userManager->getUser($id);
        if (null === $user) {
            throw new NotFoundHttpException();
        }

        $before = $this->snapshot($user);
        $actorId = $this->actorId();

        try {
            $updated = $this->userManager->updateUser($id, $data->isActive, $data->isAdmin, $actorId);
        } catch (LogicException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        $after = $this->snapshot($updated);
        $this->auditTrail->record(
            'admin.user.updated',
            'user',
            $updated->id,
            [
                'before' => $before,
                'after' => $after,
                'changed' => $this->diff($before, $after),
            ],
        );

        return $this->toOutput($updated);
    }

    private function actorId(): ?string
    {
        $actor = $this->security->getUser();

        return $actor instanceof AuthenticatedUser ? $actor->getId()->toRfc4122() : null;
    }

    /** @return array<string, mixed> */
    private function snapshot(AdminUserRecord $user): array
    {
        return [
            'email' => $user->email,
            'roles' => $user->roles,
            'isActive' => $user->isActive,
            'identityCount' => $user->identityCount,
        ];
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
        );
    }

    /** @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;
            if ($previous !== $value) {
                $changed[$key] = ['before' => $previous, 'after' => $value];
            }
        }

        return $changed;
    }
}
