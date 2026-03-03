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
use App\Admin\UI\Api\Resource\Output\AdminUserPasswordResetOutput;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminUserPasswordResetOutput>
 */
final readonly class AdminUserResetPasswordStateProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminUserManager $userManager,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminUserPasswordResetOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        try {
            $result = $this->userManager->resetPassword($id);
        } catch (LogicException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        $this->auditTrail->record(
            'admin.user.password_reset',
            'user',
            $result->user->id,
            [],
            ['channel' => 'admin_api'],
        );

        return new AdminUserPasswordResetOutput($result->user->id, $result->user->email, $result->temporaryPassword);
    }
}
