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

namespace App\Tests\Unit\Import\Application\Command;

use App\Import\Application\Command\FinalizeImportJobCommand;
use App\Import\Application\Command\FinalizeImportJobHandler;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\ImportJob;
use App\Receipt\Application\Command\CreateReceiptHandler;
use App\Receipt\Application\Command\CreateReceiptWithStationHandler;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Receipt;
use App\Station\Application\Command\CreateStationHandler;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class FinalizeImportJobHandlerTest extends TestCase
{
    public function testItFinalizesNeedsReviewJobUsingCreationPayload(): void
    {
        $job = ImportJob::createQueued(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            'local',
            '2026/02/21/file.pdf',
            'file.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );
        $job->markNeedsReview(json_encode([
            'creationPayload' => [
                'issuedAt' => '2026-02-21T10:00:00+00:00',
                'stationName' => 'Total',
                'stationStreetName' => '1 Rue A',
                'stationPostalCode' => '75001',
                'stationCity' => 'Paris',
                'latitudeMicroDegrees' => 48500000,
                'longitudeMicroDegrees' => 2300000,
                'lines' => [[
                    'fuelType' => 'diesel',
                    'quantityMilliLiters' => 10000,
                    'unitPriceDeciCentsPerLiter' => 1800,
                    'vatRatePercent' => 20,
                ]],
            ],
        ], JSON_THROW_ON_ERROR));

        $repository = new FinalizeInMemoryImportJobRepository([$job]);
        $receiptRepository = new FinalizeInMemoryReceiptRepository();
        $stationRepository = new FinalizeInMemoryStationRepository();
        $createReceiptWithStationHandler = new CreateReceiptWithStationHandler(
            new CreateReceiptHandler($receiptRepository),
            $stationRepository,
            new CreateStationHandler($stationRepository, new FinalizeNullMessageBus()),
        );
        $handler = new FinalizeImportJobHandler($repository, $createReceiptWithStationHandler);

        $updated = ($handler)(new FinalizeImportJobCommand($job->id()->toString()));

        self::assertSame('processed', $updated->status()->value);
        self::assertNotNull($updated->errorPayload());
        self::assertStringContainsString('finalizedReceiptId', (string) $updated->errorPayload());
        self::assertSame(1, $receiptRepository->savedCount);
    }

    public function testItRejectsNonNeedsReviewJob(): void
    {
        $job = ImportJob::createQueued(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            'local',
            '2026/02/21/file.pdf',
            'file.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );

        $repository = new FinalizeInMemoryImportJobRepository([$job]);
        $stationRepository = new FinalizeInMemoryStationRepository();
        $createReceiptWithStationHandler = new CreateReceiptWithStationHandler(
            new CreateReceiptHandler(new FinalizeInMemoryReceiptRepository()),
            $stationRepository,
            new CreateStationHandler($stationRepository, new FinalizeNullMessageBus()),
        );
        $handler = new FinalizeImportJobHandler($repository, $createReceiptWithStationHandler);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only needs_review jobs can be finalized.');

        ($handler)(new FinalizeImportJobCommand($job->id()->toString()));
    }
}

final class FinalizeInMemoryImportJobRepository implements ImportJobRepository
{
    /** @var array<string, ImportJob> */
    private array $items = [];

    /** @param list<ImportJob> $jobs */
    public function __construct(array $jobs)
    {
        foreach ($jobs as $job) {
            $this->items[$job->id()->toString()] = $job;
        }
    }

    public function save(ImportJob $job): void
    {
        $this->items[$job->id()->toString()] = $job;
    }

    public function get(string $id): ?ImportJob
    {
        return $this->items[$id] ?? null;
    }

    public function getForSystem(string $id): ?ImportJob
    {
        return $this->get($id);
    }

    public function findLatestByOwnerAndChecksum(string $ownerId, string $checksumSha256, ?string $excludeJobId = null): ?ImportJob
    {
        return null;
    }

    public function all(): iterable
    {
        return array_values($this->items);
    }
}

final class FinalizeInMemoryReceiptRepository implements ReceiptRepository
{
    public int $savedCount = 0;

    public function save(Receipt $receipt): void
    {
        ++$this->savedCount;
    }

    public function get(string $id): ?Receipt
    {
        return null;
    }

    public function delete(string $id): void
    {
    }

    public function all(): iterable
    {
        return [];
    }

    public function paginate(int $page, int $perPage): iterable
    {
        return [];
    }

    public function countAll(): int
    {
        return 0;
    }

    public function paginateFiltered(
        int $page,
        int $perPage,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): iterable {
        return [];
    }

    public function countFiltered(
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): int {
        return 0;
    }

    public function paginateFilteredListRows(
        int $page,
        int $perPage,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): array {
        return [];
    }

    public function listFilteredRowsForExport(
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): array {
        return [];
    }
}

final class FinalizeInMemoryStationRepository implements StationRepository
{
    /** @var array<string, Station> */
    private array $items = [];

    public function save(Station $station): void
    {
        $this->items[$station->id()->toString()] = $station;
    }

    public function get(string $id): ?Station
    {
        return $this->items[$id] ?? null;
    }

    public function getForSystem(string $id): ?Station
    {
        return $this->get($id);
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }

    public function deleteForSystem(string $id): void
    {
        $this->delete($id);
    }

    public function getByIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (isset($this->items[$id])) {
                $result[$id] = $this->items[$id];
            }
        }

        return $result;
    }

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station
    {
        foreach ($this->items as $item) {
            if (
                $item->name() === $name
                && $item->streetName() === $streetName
                && $item->postalCode() === $postalCode
                && $item->city() === $city
            ) {
                return $item;
            }
        }

        return null;
    }

    public function all(): iterable
    {
        return array_values($this->items);
    }

    public function allForSystem(): iterable
    {
        return $this->all();
    }
}

final class FinalizeNullMessageBus implements MessageBusInterface
{
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return new Envelope($message, $stamps);
    }
}
