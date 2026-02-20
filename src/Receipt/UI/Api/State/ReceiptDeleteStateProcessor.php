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

namespace App\Receipt\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\UI\Realtime\ReceiptStreamPublisher;
use App\Security\Voter\ReceiptVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class ReceiptDeleteStateProcessor implements ProcessorInterface
{
    public function __construct(
        private ReceiptRepository $repository,
        private ReceiptStreamPublisher $streamPublisher,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? null;
        if (is_string($id) && '' !== $id && Uuid::isValid($id)) {
            if (!$this->authorizationChecker->isGranted(ReceiptVoter::DELETE, $id)) {
                return;
            }

            $this->repository->delete($id);
            $this->streamPublisher->publishDeleted($id);
        }
    }
}
