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
use App\Import\Application\Command\RetryImportJobCommand;
use App\Import\Application\Command\RetryImportJobHandler;
use App\Import\UI\Api\Resource\Output\ImportJobOutput;
use App\Import\UI\Api\State\ImportJobOutputMapper;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, ImportJobOutput>
 */
final readonly class AdminImportJobRetryStateProcessor implements ProcessorInterface
{
    public function __construct(
        private RetryImportJobHandler $handler,
        private ImportJobOutputMapper $outputMapper,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ImportJobOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        try {
            $job = ($this->handler)(new RetryImportJobCommand($id));
        } catch (InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        $this->auditTrail->record(
            'admin.import.retry',
            'import_job',
            $job->id()->toString(),
            [
                'after' => ['status' => $job->status()->value],
            ],
        );

        return $this->outputMapper->map($job);
    }
}
