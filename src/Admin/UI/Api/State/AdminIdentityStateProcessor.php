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
use App\Admin\UI\Api\Resource\Input\AdminIdentityRelinkInput;
use App\Admin\UI\Api\Resource\Output\AdminIdentityOutput;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminIdentityOutput>
 */
final readonly class AdminIdentityStateProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminIdentityManager $identityManager,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminIdentityOutput
    {
        if (!$data instanceof AdminIdentityRelinkInput) {
            throw new InvalidArgumentException('Invalid identity input.');
        }

        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $identity = $this->identityManager->getIdentity($id);
        if (!$identity instanceof AdminIdentityRecord) {
            throw new NotFoundHttpException();
        }

        $before = $this->snapshot($identity);

        try {
            $updated = $this->identityManager->relinkIdentity($id, (string) $data->userId);
        } catch (LogicException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        $after = $this->snapshot($updated);
        $this->auditTrail->record(
            'admin.identity.relinked',
            'user_identity',
            $updated->id,
            [
                'before' => $before,
                'after' => $after,
                'changed' => $this->diff($before, $after),
            ],
        );

        return $this->toOutput($updated);
    }

    /** @return array<string, mixed> */
    private function snapshot(AdminIdentityRecord $identity): array
    {
        return [
            'userId' => $identity->userId,
            'userEmail' => $identity->userEmail,
            'provider' => $identity->provider,
            'subject' => $identity->subject,
            'email' => $identity->email,
        ];
    }

    private function toOutput(AdminIdentityRecord $identity): AdminIdentityOutput
    {
        return new AdminIdentityOutput(
            $identity->id,
            $identity->userId,
            $identity->userEmail,
            $identity->userRoles,
            $identity->provider,
            $identity->subject,
            $identity->email,
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
