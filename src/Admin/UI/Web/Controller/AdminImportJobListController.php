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

namespace App\Admin\UI\Web\Controller;

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminImportJobListController extends AbstractController
{
    public function __construct(private readonly ImportJobRepository $importJobRepository)
    {
    }

    #[Route('/ui/admin/imports', name: 'ui_admin_import_job_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $status = $this->readStatusFilter($request, 'status');
        $ownerId = $this->readStringFilter($request, 'ownerId');
        $source = $this->readStringFilter($request, 'source');
        $query = $this->readStringFilter($request, 'q');
        $createdFrom = $this->readDateFilter($request, 'createdFrom');
        $createdTo = $this->readDateFilter($request, 'createdTo');

        $jobs = [];
        $metrics = [
            ImportJobStatus::QUEUED->value => 0,
            ImportJobStatus::PROCESSING->value => 0,
            ImportJobStatus::NEEDS_REVIEW->value => 0,
            ImportJobStatus::FAILED->value => 0,
            ImportJobStatus::PROCESSED->value => 0,
            ImportJobStatus::DUPLICATE->value => 0,
        ];

        foreach ($this->importJobRepository->allForSystem() as $job) {
            ++$metrics[$job->status()->value];

            if (null !== $status && $job->status() !== $status) {
                continue;
            }

            if (null !== $ownerId && $job->ownerId() !== $ownerId) {
                continue;
            }

            if (null !== $source && mb_strtolower($job->storage()) !== mb_strtolower($source)) {
                continue;
            }

            if (null !== $query && !$this->matchesQuery($job, $query)) {
                continue;
            }

            if (null !== $createdFrom && $job->createdAt() < $createdFrom->setTime(0, 0, 0)) {
                continue;
            }

            if (null !== $createdTo && $job->createdAt() > $createdTo->setTime(23, 59, 59)) {
                continue;
            }

            $jobs[] = $job;
        }

        return $this->render('admin/imports/index.html.twig', [
            'jobs' => $jobs,
            'metrics' => $metrics,
            'filters' => [
                'status' => $status?->value,
                'ownerId' => $ownerId,
                'source' => $source,
                'q' => $query,
                'createdFrom' => $createdFrom?->format('Y-m-d'),
                'createdTo' => $createdTo?->format('Y-m-d'),
            ],
            'statusOptions' => array_map(static fn (ImportJobStatus $jobStatus): string => $jobStatus->value, ImportJobStatus::cases()),
        ]);
    }

    private function matchesQuery(ImportJob $job, string $query): bool
    {
        $needle = mb_strtolower($query);
        $haystack = mb_strtolower(sprintf(
            '%s %s %s %s %s',
            $job->id()->toString(),
            $job->ownerId(),
            $job->originalFilename(),
            $job->fileChecksumSha256(),
            $job->status()->value,
        ));

        return str_contains($haystack, $needle);
    }

    private function readStringFilter(Request $request, string $name): ?string
    {
        $raw = $request->query->get($name);
        if (!is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return '' === $value ? null : $value;
    }

    private function readStatusFilter(Request $request, string $name): ?ImportJobStatus
    {
        $value = $this->readStringFilter($request, $name);
        if (null === $value) {
            return null;
        }

        foreach (ImportJobStatus::cases() as $status) {
            if ($status->value === $value) {
                return $status;
            }
        }

        return null;
    }

    private function readDateFilter(Request $request, string $name): ?DateTimeImmutable
    {
        $value = $this->readStringFilter($request, $name);
        if (null === $value) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false === $parsed) {
            return null;
        }

        return $parsed;
    }
}
