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
use ApiPlatform\State\ProviderInterface;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Security\Voter\ReceiptVoter;
use App\Receipt\Domain\Receipt;
use App\Receipt\UI\Api\Resource\Output\ReceiptLineOutput;
use App\Receipt\UI\Api\Resource\Output\ReceiptOutput;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ReceiptOutput>
 */
final readonly class ReceiptStateProvider implements ProviderInterface
{
    public function __construct(
        private ReceiptRepository $repository,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $id = $uriVariables['id'] ?? null;
        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                return null;
            }

            if (!$this->authorizationChecker->isGranted(ReceiptVoter::VIEW, $id)) {
                return null;
            }

            $receipt = $this->repository->get($id);

            return $receipt ? $this->toOutput($receipt) : null;
        }

        $resources = [];
        foreach ($this->repository->all() as $receipt) {
            $resources[] = $this->toOutput($receipt);
        }

        return $resources;
    }

    private function toOutput(Receipt $receipt): ReceiptOutput
    {
        $lines = [];
        foreach ($receipt->lines() as $line) {
            $lines[] = new ReceiptLineOutput(
                $line->fuelType()->value,
                $line->quantityMilliLiters(),
                $line->unitPriceDeciCentsPerLiter(),
                $line->lineTotalCents(),
                $line->vatRatePercent(),
                $line->vatAmountCents(),
            );
        }

        return new ReceiptOutput(
            $receipt->id()->toString(),
            $receipt->issuedAt(),
            $receipt->totalCents(),
            $receipt->vatAmountCents(),
            Uuid::fromString($receipt->stationId()?->toString() ?? ''),
            $lines,
        );
    }
}
