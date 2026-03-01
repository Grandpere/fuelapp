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
use App\Admin\UI\Api\Resource\Output\AdminUserVerificationDispatchOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminUserVerificationDispatchOutput>
 */
final readonly class AdminUserResendVerificationStateProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminUserManager $userManager,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminUserVerificationDispatchOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $user = $this->userManager->getUser($id);
        if (!$user instanceof AdminUserRecord) {
            throw new NotFoundHttpException();
        }

        $this->auditTrail->record(
            'admin.user.verification_resend_requested',
            'user',
            $user->id,
            [],
            ['channel' => 'admin_api', 'mailer' => 'not_configured'],
        );

        return new AdminUserVerificationDispatchOutput($user->id, $user->email, 'queued_locally_without_mailer');
    }
}
