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

namespace App\Tests\Unit\Receipt\Application\Command;

use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestion;
use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestionReader;
use App\Receipt\Application\Command\CreateReceiptHandler;
use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationHandler;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\Domain\Receipt;
use App\Station\Application\Command\CreateStationCommand;
use App\Station\Application\Command\CreateStationHandler;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use App\Station\Domain\ValueObject\StationId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateReceiptWithStationHandlerTest extends TestCase
{
    public function testItCreatesReceiptWithExistingStation(): void
    {
        $station = Station::reconstitute(
            StationId::fromString('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01'),
            'Total',
            'Rue A',
            '75001',
            'Paris',
            null,
            null,
        );

        $stationRepo = new InMemoryStationRepository($station);
        $receiptRepo = new InMemoryReceiptRepository();

        $receiptHandler = new CreateReceiptHandler($receiptRepo);
        $stationHandler = new CreateStationHandler($stationRepo, new NullMessageBus());

        $handler = new CreateReceiptWithStationHandler($receiptHandler, $stationRepo, $stationHandler, new InMemoryPublicFuelStationSuggestionReader());

        $command = new CreateReceiptWithStationCommand(
            new DateTimeImmutable('2026-02-16T12:00:00+00:00'),
            [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
            'Total',
            'Rue A',
            '75001',
            'Paris',
            null,
            null,
        );

        $receipt = ($handler)($command);

        self::assertInstanceOf(Receipt::class, $receipt);
        self::assertSame('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01', $receipt->stationId()?->toString());
    }

    public function testItCreatesStationIfMissing(): void
    {
        $stationRepo = new InMemoryStationRepository(null);
        $receiptRepo = new InMemoryReceiptRepository();

        $receiptHandler = new CreateReceiptHandler($receiptRepo);
        $stationHandler = new CreateStationHandler($stationRepo, new NullMessageBus());

        $handler = new CreateReceiptWithStationHandler($receiptHandler, $stationRepo, $stationHandler, new InMemoryPublicFuelStationSuggestionReader());

        $command = new CreateReceiptWithStationCommand(
            new DateTimeImmutable('2026-02-16T12:00:00+00:00'),
            [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
            'Total',
            'Rue A',
            '75001',
            'Paris',
            null,
            null,
        );

        $receipt = ($handler)($command);

        self::assertInstanceOf(Receipt::class, $receipt);
        self::assertNotNull($receipt->stationId());
    }

    public function testItUsesSelectedStationIdBeforeIdentityLookup(): void
    {
        $selectedStation = Station::reconstitute(
            StationId::fromString('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01'),
            'Selected Station',
            '1 Picker Road',
            '51120',
            'SEZANNE',
            null,
            null,
        );

        $stationRepo = new InMemoryStationRepository($selectedStation);
        $stationRepo->setIdentityResult(null);
        $receiptRepo = new InMemoryReceiptRepository();

        $handler = new CreateReceiptWithStationHandler(
            new CreateReceiptHandler($receiptRepo),
            $stationRepo,
            new CreateStationHandler($stationRepo, new NullMessageBus()),
            new InMemoryPublicFuelStationSuggestionReader(),
        );

        $receipt = $handler(new CreateReceiptWithStationCommand(
            new DateTimeImmutable('2026-04-29T12:00:00+00:00'),
            [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
            'Typed Name',
            'Typed Street',
            '75001',
            'Paris',
            null,
            null,
            selectedStationId: '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
        ));

        self::assertSame('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01', $receipt->stationId()?->toString());
    }

    public function testItThrowsWhenSelectedStationDoesNotExist(): void
    {
        $stationRepo = new InMemoryStationRepository(null);
        $receiptRepo = new InMemoryReceiptRepository();

        $handler = new CreateReceiptWithStationHandler(
            new CreateReceiptHandler($receiptRepo),
            $stationRepo,
            new CreateStationHandler($stationRepo, new NullMessageBus()),
            new InMemoryPublicFuelStationSuggestionReader(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Selected station was not found.');

        $handler(new CreateReceiptWithStationCommand(
            new DateTimeImmutable('2026-04-29T12:00:00+00:00'),
            [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
            'Typed Name',
            'Typed Street',
            '75001',
            'Paris',
            null,
            null,
            selectedStationId: '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b99',
        ));
    }

    public function testItIsIdempotentOnStationCreationRace(): void
    {
        $existingStation = Station::reconstitute(
            StationId::fromString('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01'),
            'Total',
            'Rue A',
            '75001',
            'Paris',
            null,
            null,
        );

        $stationRepo = new InMemoryStationRepository(null);
        $stationRepo->setFallback($existingStation);
        $receiptRepo = new InMemoryReceiptRepository();

        $receiptHandler = new CreateReceiptHandler($receiptRepo);
        $stationHandler = new FailingCreateStationHandler($stationRepo);

        $handler = new CreateReceiptWithStationHandler($receiptHandler, $stationRepo, $stationHandler, new InMemoryPublicFuelStationSuggestionReader());

        $command = new CreateReceiptWithStationCommand(
            new DateTimeImmutable('2026-02-16T12:00:00+00:00'),
            [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
            'Total',
            'Rue A',
            '75001',
            'Paris',
            null,
            null,
        );

        $receipt = ($handler)($command);

        self::assertSame('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01', $receipt->stationId()?->toString());
    }

    public function testItCreatesStationFromSelectedPublicSuggestionWhenNoInternalStationMatches(): void
    {
        $stationRepo = new InMemoryStationRepository(null);
        $stationRepo->setIdentityResult(null);
        $receiptRepo = new InMemoryReceiptRepository();

        $handler = new CreateReceiptWithStationHandler(
            new CreateReceiptHandler($receiptRepo),
            $stationRepo,
            new CreateStationHandler($stationRepo, new NullMessageBus()),
            new InMemoryPublicFuelStationSuggestionReader([
                'public-1' => new PublicFuelStationSuggestion('public-1', '40 Rue Robert Schuman', '40 Rue Robert Schuman', '5751', 'FRISANGE', 49569000, 4230000),
            ]),
        );

        $receipt = $handler(new CreateReceiptWithStationCommand(
            new DateTimeImmutable('2026-04-29T12:00:00+00:00'),
            [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
            'Typed Name',
            'Typed Street',
            '5751',
            'FRISANGE',
            null,
            null,
            selectedSuggestionType: 'public',
            selectedSuggestionId: 'public-1',
        ));

        self::assertNotNull($receipt->stationId());
        $stationId = $receipt->stationId();
        self::assertSame('40 Rue Robert Schuman', $stationRepo->get($stationId->toString())?->name());
    }

    public function testItThrowsWhenSelectedPublicSuggestionDoesNotExist(): void
    {
        $stationRepo = new InMemoryStationRepository(null);
        $receiptRepo = new InMemoryReceiptRepository();

        $handler = new CreateReceiptWithStationHandler(
            new CreateReceiptHandler($receiptRepo),
            $stationRepo,
            new CreateStationHandler($stationRepo, new NullMessageBus()),
            new InMemoryPublicFuelStationSuggestionReader(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Selected public station was not found.');

        $handler(new CreateReceiptWithStationCommand(
            new DateTimeImmutable('2026-04-29T12:00:00+00:00'),
            [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
            'Typed Name',
            'Typed Street',
            '5751',
            'FRISANGE',
            null,
            null,
            selectedSuggestionType: 'public',
            selectedSuggestionId: 'public-missing',
        ));
    }
}

final class InMemoryPublicFuelStationSuggestionReader implements PublicFuelStationSuggestionReader
{
    /** @param array<string, PublicFuelStationSuggestion> $items */
    public function __construct(private array $items = [])
    {
    }

    public function search(\App\Station\Application\Suggestion\StationSuggestionQuery $query, int $limit): array
    {
        return array_slice(array_values($this->items), 0, $limit);
    }

    public function getBySourceId(string $sourceId): ?PublicFuelStationSuggestion
    {
        return $this->items[$sourceId] ?? null;
    }
}

final class InMemoryStationRepository implements StationRepository
{
    private ?Station $station;
    private ?Station $fallback = null;
    private ?Station $identityResult = null;

    public function __construct(?Station $station)
    {
        $this->station = $station;
    }

    public function setFallback(Station $station): void
    {
        $this->fallback = $station;
    }

    public function setIdentityResult(?Station $station): void
    {
        $this->identityResult = $station;
    }

    public function save(Station $station): void
    {
        $this->station = $station;
    }

    public function get(string $id): ?Station
    {
        return $this->station && $this->station->id()->toString() === $id ? $this->station : null;
    }

    public function getForSystem(string $id): ?Station
    {
        return $this->get($id);
    }

    public function delete(string $id): void
    {
        if (null !== $this->station && $this->station->id()->toString() === $id) {
            $this->station = null;
        }
    }

    public function deleteForSystem(string $id): void
    {
        $this->delete($id);
    }

    public function getByIds(array $ids): array
    {
        if (null === $this->station) {
            return [];
        }

        if (!in_array($this->station->id()->toString(), $ids, true)) {
            return [];
        }

        return [$this->station->id()->toString() => $this->station];
    }

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station
    {
        if (null !== $this->identityResult) {
            return $this->identityResult;
        }

        if (null !== $this->station) {
            return $this->station;
        }

        return $this->fallback;
    }

    public function all(): iterable
    {
        return $this->station ? [$this->station] : [];
    }

    public function allForSystem(): iterable
    {
        return $this->all();
    }
}

final class InMemoryReceiptRepository implements ReceiptRepository
{
    /** @var array<string, Receipt> */
    private array $items = [];

    public function save(Receipt $receipt): void
    {
        $this->items[$receipt->id()->toString()] = $receipt;
    }

    public function saveForOwner(Receipt $receipt, string $ownerId): void
    {
        $this->save($receipt);
    }

    public function get(string $id): ?Receipt
    {
        return $this->items[$id] ?? null;
    }

    public function getForSystem(string $id): ?Receipt
    {
        return $this->get($id);
    }

    public function ownerIdForSystem(string $id): ?string
    {
        return null;
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }

    public function deleteForSystem(string $id): void
    {
        $this->delete($id);
    }

    public function all(): iterable
    {
        return array_values($this->items);
    }

    public function allForSystem(): iterable
    {
        return $this->all();
    }

    public function paginate(int $page, int $perPage): iterable
    {
        return array_slice(array_values($this->items), max(0, ($page - 1) * $perPage), $perPage);
    }

    public function countAll(): int
    {
        return count($this->items);
    }

    public function paginateFiltered(
        int $page,
        int $perPage,
        ?string $vehicleId,
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
        return $this->paginate($page, $perPage);
    }

    public function countFiltered(
        ?string $vehicleId,
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
        return $this->countAll();
    }

    public function paginateFilteredListRows(
        int $page,
        int $perPage,
        ?string $vehicleId,
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
        $receipts = $this->paginate($page, $perPage);
        $rows = [];
        foreach ($receipts as $receipt) {
            $line = $receipt->lines()[0] ?? null;
            $rows[] = [
                'id' => $receipt->id()->toString(),
                'issuedAt' => $receipt->issuedAt(),
                'totalCents' => $receipt->totalCents(),
                'vatAmountCents' => $receipt->vatAmountCents(),
                'odometerKilometers' => $receipt->odometerKilometers(),
                'stationName' => null,
                'stationStreetName' => null,
                'stationPostalCode' => null,
                'stationCity' => null,
                'fuelType' => $line?->fuelType()->value,
                'quantityMilliLiters' => $line?->quantityMilliLiters(),
                'unitPriceDeciCentsPerLiter' => $line?->unitPriceDeciCentsPerLiter(),
                'vatRatePercent' => $line?->vatRatePercent(),
            ];
        }

        return $rows;
    }

    public function listFilteredRowsForExport(
        ?string $vehicleId,
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
        return $this->paginateFilteredListRows(
            1,
            1000000,
            $vehicleId,
            $stationId,
            $issuedFrom,
            $issuedTo,
            $sortBy,
            $sortDirection,
            $fuelType,
            $quantityMilliLitersMin,
            $quantityMilliLitersMax,
            $unitPriceDeciCentsPerLiterMin,
            $unitPriceDeciCentsPerLiterMax,
            $vatRatePercent,
        );
    }

    public function maxOdometerKilometersForOwnerAndVehicle(string $ownerId, string $vehicleId): ?int
    {
        return null;
    }
}

final readonly class FailingCreateStationHandler extends CreateStationHandler
{
    public function __construct(StationRepository $repository)
    {
        parent::__construct($repository, new NullMessageBus());
    }

    public function __invoke(CreateStationCommand $command): Station
    {
        throw new RuntimeException('Unique constraint violation');
    }
}

final class NullMessageBus implements MessageBusInterface
{
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return new Envelope($message, $stamps);
    }
}
