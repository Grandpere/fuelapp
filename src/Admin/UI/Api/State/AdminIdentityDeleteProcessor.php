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
use App\Admin\Application\Identity\AdminIdentityManager;
use App\Admin\Application\Identity\AdminIdentityRecord;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class AdminIdentityDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminIdentityManager $identityManager,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $identity = $this->identityManager->getIdentity($id);
        if (!$identity instanceof AdminIdentityRecord) {
            throw new NotFoundHttpException();
        }

        $before = [
            'userId' => $identity->userId,
            'userEmail' => $identity->userEmail,
            'provider' => $identity->provider,
            'subject' => $identity->subject,
            'email' => $identity->email,
        ];

        try {
            $this->identityManager->unlinkIdentity($id);
        } catch (LogicException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        $this->auditTrail->record(
            'admin.identity.unlinked',
            'user_identity',
            $id,
            [
                'before' => $before,
            ],
        );
    }
}
