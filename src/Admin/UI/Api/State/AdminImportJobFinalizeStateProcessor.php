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
use App\Import\Application\Command\FinalizeImportJobCommand;
use App\Import\Application\Command\FinalizeImportJobHandler;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\UI\Api\Resource\Input\ImportFinalizeInput;
use App\Import\UI\Api\Resource\Output\ImportJobOutput;
use App\Import\UI\Api\State\ImportJobOutputMapper;
use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Domain\Enum\FuelType;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;
use ValueError;

/**
 * @implements ProcessorInterface<ImportFinalizeInput, ImportJobOutput>
 */
final readonly class AdminImportJobFinalizeStateProcessor implements ProcessorInterface
{
    public function __construct(
        private ImportJobRepository $repository,
        private FinalizeImportJobHandler $handler,
        private ImportJobOutputMapper $outputMapper,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ImportJobOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $job = $this->repository->getForSystem($id);
        if (null === $job) {
            throw new NotFoundHttpException();
        }

        try {
            $updated = ($this->handler)(new FinalizeImportJobCommand(
                $job->id()->toString(),
                $data->issuedAt,
                $this->mapLines($data),
                $data->stationName,
                $data->stationStreetName,
                $data->stationPostalCode,
                $data->stationCity,
                $data->latitudeMicroDegrees,
                $data->longitudeMicroDegrees,
            ));
        } catch (InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->outputMapper->map($updated);
    }

    /** @return list<CreateReceiptLineCommand>|null */
    private function mapLines(ImportFinalizeInput $input): ?array
    {
        if (null === $input->lines) {
            return null;
        }

        $lines = [];
        foreach ($input->lines as $line) {
            if (
                null === $line->fuelType
                || null === $line->quantityMilliLiters
                || null === $line->unitPriceDeciCentsPerLiter
                || null === $line->vatRatePercent
            ) {
                throw new UnprocessableEntityHttpException('Line fields are required when lines are provided.');
            }

            try {
                $lines[] = new CreateReceiptLineCommand(
                    FuelType::from($line->fuelType),
                    $line->quantityMilliLiters,
                    $line->unitPriceDeciCentsPerLiter,
                    $line->vatRatePercent,
                );
            } catch (ValueError $e) {
                throw new UnprocessableEntityHttpException('Invalid fuel type.', $e);
            }
        }

        return $lines;
    }
}
