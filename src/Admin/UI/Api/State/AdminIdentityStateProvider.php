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
use App\Admin\Application\Identity\AdminIdentityManager;
use App\Admin\Application\Identity\AdminIdentityRecord;
use App\Admin\UI\Api\Resource\Output\AdminIdentityOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminIdentityOutput>
 */
final readonly class AdminIdentityStateProvider implements ProviderInterface
{
    public function __construct(private AdminIdentityManager $identityManager)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $id = $uriVariables['id'] ?? null;
        if (null !== $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $identity = $this->identityManager->getIdentity($id);
            if (!$identity instanceof AdminIdentityRecord) {
                throw new NotFoundHttpException();
            }

            return $this->toOutput($identity);
        }

        $items = $this->identityManager->listIdentities(
            $this->readFilter($context, 'q'),
            $this->readFilter($context, 'provider'),
            $this->readUuidFilter($context, 'userId'),
        );

        $output = [];
        foreach ($items as $item) {
            $output[] = $this->toOutput($item);
        }

        return $output;
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
    private function readUuidFilter(array $context, string $name): ?string
    {
        $value = $this->readFilter($context, $name);
        if (null === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $value;
    }
}
