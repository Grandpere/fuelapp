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

namespace App\Receipt\UI\Web\Controller;

use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestionReader;
use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationHandler;
use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\UI\Api\Resource\Input\ReceiptInput;
use App\Receipt\UI\Api\Resource\Input\ReceiptLineInput;
use App\Receipt\UI\Realtime\ReceiptStreamPublisher;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Station\Application\Repository\StationRepository;
use App\Station\Application\Suggestion\StationSuggestion;
use App\Station\Application\Suggestion\StationSuggestionQuery;
use App\Station\Application\Suggestion\StationSuggestionReader;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use UnexpectedValueException;

final class CreateReceiptController extends AbstractController
{
    public function __construct(
        private readonly CreateReceiptWithStationHandler $createReceiptWithStationHandler,
        private readonly StationRepository $stationRepository,
        private readonly StationSuggestionReader $stationSuggestionReader,
        private readonly PublicFuelStationSuggestionReader $publicFuelStationSuggestionReader,
        private readonly ReceiptStreamPublisher $streamPublisher,
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly ValidatorInterface $validator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/receipts/new', name: 'ui_receipt_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $isTurboFrameRequest = $request->headers->has('Turbo-Frame');
        $formData = $this->defaultFormData($request, $ownerId);
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData = $this->extractFormData($request, $ownerId);
            if (!$this->isCsrfTokenValid('receipt_new', $formData['_token'])) {
                $errors[] = 'Jeton CSRF invalide.';
            } elseif ($this->isStationLookupRequest($request)) {
                if (!$this->hasStationSearchContext($formData)) {
                    $errors[] = 'Enter a station search term or fill at least one station field before looking for matches.';
                } else {
                    return new RedirectResponse($this->generateUrl('ui_receipt_new', $this->stationLookupQueryParams($formData)), Response::HTTP_SEE_OTHER);
                }
            } else {
                $errors = $this->validateSelectedSuggestionChoice($formData);
                $this->hydrateSelectedStationFields($formData);
                $errors = $this->validateFormData($formData, $ownerId);
                if ([] === $errors) {
                    $this->persistReceiptFromForm($formData, $ownerId);
                    $this->addFlash('success', 'Receipt created.');

                    return new RedirectResponse($this->generateUrl('ui_receipt_index'), Response::HTTP_SEE_OTHER);
                }
            }
        }

        $stationSuggestions = $this->stationSuggestions($formData);
        $this->clearStaleSelectedSuggestion($formData, $stationSuggestions);

        $response = $this->render($isTurboFrameRequest ? 'receipt/_form.html.twig' : 'receipt/new.html.twig', [
            'formData' => $formData,
            'errors' => $errors,
            'fuelTypes' => array_map(static fn (FuelType $fuelType): string => $fuelType->value, FuelType::cases()),
            'vehicleOptions' => $this->vehicleOptions($ownerId),
            'existingStationSuggestions' => array_values(array_filter($stationSuggestions, static fn (StationSuggestion $suggestion): bool => 'station' === $suggestion->sourceType)),
            'publicStationSuggestions' => array_values(array_filter($stationSuggestions, static fn (StationSuggestion $suggestion): bool => 'public' === $suggestion->sourceType)),
            'stationLookupPerformed' => $this->stationLookupPerformed($request),
            'stationLookupCount' => count($stationSuggestions),
            'csrfToken' => $this->csrfTokenManager->getToken('receipt_new')->getValue(),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /** @return array<string, string> */
    private function defaultFormData(Request $request, string $ownerId): array
    {
        $prefilledStation = $this->readPrefilledStation($request);

        return [
            'issuedAt' => $this->queryValue($request, 'issuedAt', new DateTimeImmutable()->format('Y-m-d\TH:i')),
            'vehicleId' => $this->queryValue($request, 'vehicleId', $this->readPrefilledVehicleId($request, $ownerId) ?? ''),
            'fuelType' => $this->queryValue($request, 'fuelType', FuelType::DIESEL->value),
            'quantityLiters' => $this->queryValue($request, 'quantityLiters', ''),
            'unitPriceEurosPerLiter' => $this->queryValue($request, 'unitPriceEurosPerLiter', ''),
            'vatRatePercent' => $this->queryValue($request, 'vatRatePercent', '20'),
            'stationName' => $this->queryValue($request, 'stationName', $prefilledStation?->name() ?? ''),
            'stationStreetName' => $this->queryValue($request, 'stationStreetName', $prefilledStation?->streetName() ?? ''),
            'stationPostalCode' => $this->queryValue($request, 'stationPostalCode', $prefilledStation?->postalCode() ?? ''),
            'stationCity' => $this->queryValue($request, 'stationCity', $prefilledStation?->city() ?? ''),
            'selectedStationId' => $this->queryValue($request, 'selectedStationId', $prefilledStation?->id()->toString() ?? ''),
            'selectedSuggestion' => $this->queryValue($request, 'selectedSuggestion', null !== $prefilledStation ? 'station:'.$prefilledStation->id()->toString() : ''),
            'stationSearch' => $this->queryValue($request, 'stationSearch', $prefilledStation?->name() ?? ''),
            'latitudeMicroDegrees' => $this->queryValue($request, 'latitudeMicroDegrees', null !== $prefilledStation?->latitudeMicroDegrees() ? (string) $prefilledStation->latitudeMicroDegrees() : ''),
            'longitudeMicroDegrees' => $this->queryValue($request, 'longitudeMicroDegrees', null !== $prefilledStation?->longitudeMicroDegrees() ? (string) $prefilledStation->longitudeMicroDegrees() : ''),
            'odometerKilometers' => $this->queryValue($request, 'odometerKilometers', ''),
            '_token' => '',
        ];
    }

    /** @return array<string, string> */
    private function extractFormData(Request $request, string $ownerId): array
    {
        $data = $this->defaultFormData($request, $ownerId);

        foreach (array_keys($data) as $key) {
            $value = $request->request->get($key, '');
            $data[$key] = is_scalar($value) ? (string) $value : '';
        }

        return $data;
    }

    /** @param array<string, string> $formData
     * @return list<string>
     */
    private function validateFormData(array $formData, string $ownerId): array
    {
        $selectedStationErrors = $this->validateSelectedSuggestionChoice($formData);
        if ([] !== $selectedStationErrors) {
            return $selectedStationErrors;
        }

        $issuedAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $formData['issuedAt']) ?: null;
        $quantityMilliLiters = $this->parseScaledDecimalToInt($formData['quantityLiters'], 1000, 3);
        $unitPriceDeciCentsPerLiter = $this->parseScaledDecimalToInt($formData['unitPriceEurosPerLiter'], 1000, 3);
        $vatRatePercent = $this->toNullableInt($formData['vatRatePercent']);
        $vehicleId = $this->nullIfEmpty($formData['vehicleId']);

        $lineInput = new ReceiptLineInput(
            $this->nullIfEmpty($formData['fuelType']),
            $quantityMilliLiters,
            $unitPriceDeciCentsPerLiter,
            $vatRatePercent,
        );

        $receiptInput = new ReceiptInput(
            $issuedAt,
            [$lineInput],
            $this->nullIfEmpty($formData['stationName']),
            $this->nullIfEmpty($formData['stationStreetName']),
            $this->nullIfEmpty($formData['stationPostalCode']),
            $this->nullIfEmpty($formData['stationCity']),
            $this->toNullableInt($formData['latitudeMicroDegrees']),
            $this->toNullableInt($formData['longitudeMicroDegrees']),
            $vehicleId,
            $this->toNullableInt($formData['odometerKilometers']),
        );

        $errors = [];
        if (null === $issuedAt) {
            $errors[] = 'Invalid issue date.';
        }
        if (null === $quantityMilliLiters) {
            $errors[] = 'Quantity must be a valid liters value, for example 40.40.';
        }
        if (null === $unitPriceDeciCentsPerLiter) {
            $errors[] = 'Unit price must be a valid €/L value, for example 1.769.';
        }
        if (null === $vatRatePercent) {
            $errors[] = 'VAT must be an integer percentage.';
        }
        if (null !== $vehicleId && !$this->vehicleRepository->belongsToOwner($vehicleId, $ownerId)) {
            $errors[] = 'Vehicle not found.';
        }

        foreach ($this->validator->validate($receiptInput) as $violation) {
            $errors[] = (string) $violation->getMessage();
        }

        return array_values(array_unique($errors));
    }

    /** @param array<string, string> $formData */
    private function persistReceiptFromForm(array $formData, string $ownerId): void
    {
        $quantityMilliLiters = $this->parseScaledDecimalToRequiredInt($formData['quantityLiters'], 1000, 3, 'quantityLiters');
        $unitPriceDeciCentsPerLiter = $this->parseScaledDecimalToRequiredInt($formData['unitPriceEurosPerLiter'], 1000, 3, 'unitPriceEurosPerLiter');

        $line = new CreateReceiptLineCommand(
            FuelType::from($formData['fuelType']),
            $quantityMilliLiters,
            $unitPriceDeciCentsPerLiter,
            $this->toRequiredInt($formData['vatRatePercent'], 'vatRatePercent'),
        );

        $command = new CreateReceiptWithStationCommand(
            DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $formData['issuedAt']) ?: new DateTimeImmutable(),
            [$line],
            $formData['stationName'],
            $formData['stationStreetName'],
            $formData['stationPostalCode'],
            $formData['stationCity'],
            $this->toNullableInt($formData['latitudeMicroDegrees']),
            $this->toNullableInt($formData['longitudeMicroDegrees']),
            $this->nullIfEmpty($formData['vehicleId']),
            $ownerId,
            odometerKilometers: $this->toNullableInt($formData['odometerKilometers']),
            selectedStationId: $this->nullIfEmpty($formData['selectedStationId']),
            selectedSuggestionType: $this->selectedSuggestionType($formData),
            selectedSuggestionId: $this->selectedSuggestionId($formData),
        );

        $receipt = ($this->createReceiptWithStationHandler)($command);
        $station = null;
        if (null !== $receipt->stationId()) {
            $station = $this->stationRepository->get($receipt->stationId()->toString());
        }

        $this->streamPublisher->publishCreated($receipt, $station);
    }

    private function toNullableInt(?string $value): ?int
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (null === $int) {
            return null;
        }

        return $int;
    }

    private function toRequiredInt(string $value, string $field): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (null === $int) {
            throw new UnexpectedValueException(sprintf('Expected integer for %s.', $field));
        }

        return $int;
    }

    private function parseScaledDecimalToRequiredInt(string $value, int $scale, int $maxDecimals, string $field): int
    {
        $parsed = $this->parseScaledDecimalToInt($value, $scale, $maxDecimals);
        if (null === $parsed) {
            throw new UnexpectedValueException(sprintf('Expected decimal value for %s.', $field));
        }

        return $parsed;
    }

    private function parseScaledDecimalToInt(?string $value, int $scale, int $maxDecimals): ?int
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim(str_replace(' ', '', str_replace(',', '.', $value)));
        if ('' === $normalized) {
            return null;
        }

        if (!preg_match('/^\d+(?:\.\d{1,'.$maxDecimals.'})?$/', $normalized)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad($fraction, $maxDecimals, '0');

        return ((int) $whole * $scale) + (int) substr($fraction, 0, $maxDecimals);
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $stringValue = trim($value);

        return '' === $stringValue ? null : $stringValue;
    }

    /** @return array<string, string> */
    private function vehicleOptions(string $ownerId): array
    {
        $options = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            if ($vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $options[$vehicle->id()->toString()] = sprintf('%s (%s)', $vehicle->name(), $vehicle->plateNumber());
        }

        return $options;
    }

    private function isStationLookupRequest(Request $request): bool
    {
        if ('1' === (string) $request->request->get('_station_lookup_requested')) {
            return true;
        }

        return $request->request->has('_station_lookup');
    }

    private function stationLookupPerformed(Request $request): bool
    {
        return '1' === $this->queryValue($request, 'station_lookup', '0');
    }

    /**
     * @param array<string, string> $formData
     *
     * @return list<string>
     */
    private function validateSelectedSuggestionChoice(array $formData): array
    {
        $selectedSuggestionType = $this->selectedSuggestionType($formData);
        $selectedSuggestionId = $this->selectedSuggestionId($formData);

        if (null === $selectedSuggestionType || null === $selectedSuggestionId) {
            return [];
        }

        if ('station' === $selectedSuggestionType) {
            if (!Uuid::isValid($selectedSuggestionId) || null === $this->stationRepository->get($selectedSuggestionId)) {
                return ['Selected station was not found.'];
            }

            return [];
        }

        if ('public' === $selectedSuggestionType) {
            if (null === $this->publicFuelStationSuggestionReader->getBySourceId($selectedSuggestionId)) {
                return ['Selected public station was not found.'];
            }

            return [];
        }

        return ['Selected station suggestion is invalid.'];
    }

    /**
     * @param array<string, string> $formData
     */
    private function hydrateSelectedStationFields(array &$formData): void
    {
        $selectedSuggestionType = $this->selectedSuggestionType($formData);
        $selectedSuggestionId = $this->selectedSuggestionId($formData);
        if (null === $selectedSuggestionType || null === $selectedSuggestionId) {
            return;
        }

        if ('station' === $selectedSuggestionType) {
            $station = $this->stationRepository->get($selectedSuggestionId);
            if (null === $station) {
                return;
            }

            $formData['stationName'] = $station->name();
            $formData['stationStreetName'] = $station->streetName();
            $formData['stationPostalCode'] = $station->postalCode();
            $formData['stationCity'] = $station->city();
            $formData['stationSearch'] = sprintf('%s - %s, %s %s', $station->name(), $station->streetName(), $station->postalCode(), $station->city());
            $formData['latitudeMicroDegrees'] = null !== $station->latitudeMicroDegrees() ? (string) $station->latitudeMicroDegrees() : '';
            $formData['longitudeMicroDegrees'] = null !== $station->longitudeMicroDegrees() ? (string) $station->longitudeMicroDegrees() : '';
            $formData['selectedStationId'] = $station->id()->toString();

            return;
        }

        $publicStation = $this->publicFuelStationSuggestionReader->getBySourceId($selectedSuggestionId);
        if (null === $publicStation) {
            return;
        }

        $formData['stationName'] = $publicStation->name;
        $formData['stationStreetName'] = $publicStation->streetName;
        $formData['stationPostalCode'] = $publicStation->postalCode;
        $formData['stationCity'] = $publicStation->city;
        $formData['latitudeMicroDegrees'] = null !== $publicStation->latitudeMicroDegrees ? (string) $publicStation->latitudeMicroDegrees : '';
        $formData['longitudeMicroDegrees'] = null !== $publicStation->longitudeMicroDegrees ? (string) $publicStation->longitudeMicroDegrees : '';
        $formData['selectedStationId'] = '';
        $formData['stationSearch'] = trim(implode(' ', array_filter([$publicStation->name, $publicStation->postalCode, $publicStation->city])));
    }

    /**
     * @param array<string, string> $formData
     *
     * @return list<StationSuggestion>
     */
    private function stationSuggestions(array $formData): array
    {
        if (!$this->hasStationSearchContext($formData)) {
            return [];
        }

        return $this->stationSuggestionReader->search(new StationSuggestionQuery(
            $this->nullIfEmpty($formData['stationSearch']),
            $this->nullIfEmpty($formData['stationName']),
            $this->nullIfEmpty($formData['stationStreetName']),
            $this->nullIfEmpty($formData['stationPostalCode']),
            $this->nullIfEmpty($formData['stationCity']),
        ));
    }

    /**
     * @param array<string, string>   $formData
     * @param list<StationSuggestion> $stationSuggestions
     */
    private function clearStaleSelectedSuggestion(array &$formData, array $stationSuggestions): void
    {
        if ([] !== $stationSuggestions) {
            return;
        }

        if ('public' === $this->selectedSuggestionType($formData)) {
            $formData['selectedSuggestion'] = '';
        }
    }

    /**
     * @param array<string, string> $formData
     */
    private function hasStationSearchContext(array $formData): bool
    {
        foreach (['stationSearch', 'stationName', 'stationStreetName', 'stationPostalCode', 'stationCity', 'selectedStationId', 'selectedSuggestion'] as $key) {
            if ('' !== trim($formData[$key] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $formData
     */
    private function selectedSuggestionType(array $formData): ?string
    {
        $selectedSuggestion = $this->nullIfEmpty($formData['selectedSuggestion']);
        if (null === $selectedSuggestion) {
            $selectedStationId = $this->nullIfEmpty($formData['selectedStationId']);

            return null === $selectedStationId ? null : 'station';
        }

        [$type] = explode(':', $selectedSuggestion, 2);
        $trimmed = trim($type);

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * @param array<string, string> $formData
     */
    private function selectedSuggestionId(array $formData): ?string
    {
        $selectedSuggestion = $this->nullIfEmpty($formData['selectedSuggestion']);
        if (null === $selectedSuggestion) {
            return $this->nullIfEmpty($formData['selectedStationId']);
        }

        $parts = explode(':', $selectedSuggestion, 2);
        $id = $parts[1] ?? null;
        if (null === $id) {
            return null;
        }

        $trimmed = trim($id);

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * @param array<string, string> $formData
     *
     * @return array<string, string>
     */
    private function stationLookupQueryParams(array $formData): array
    {
        return [
            'station_lookup' => '1',
            'issuedAt' => $formData['issuedAt'],
            'vehicleId' => $formData['vehicleId'],
            'fuelType' => $formData['fuelType'],
            'quantityLiters' => $formData['quantityLiters'],
            'unitPriceEurosPerLiter' => $formData['unitPriceEurosPerLiter'],
            'vatRatePercent' => $formData['vatRatePercent'],
            'stationSearch' => $formData['stationSearch'],
            'stationName' => $formData['stationName'],
            'stationStreetName' => $formData['stationStreetName'],
            'stationPostalCode' => $formData['stationPostalCode'],
            'stationCity' => $formData['stationCity'],
            'selectedSuggestion' => $formData['selectedSuggestion'],
            'latitudeMicroDegrees' => $formData['latitudeMicroDegrees'],
            'longitudeMicroDegrees' => $formData['longitudeMicroDegrees'],
            'odometerKilometers' => $formData['odometerKilometers'],
        ];
    }

    private function queryValue(Request $request, string $key, string $default): string
    {
        $raw = $request->query->get($key);
        if (!is_scalar($raw)) {
            return $default;
        }

        return (string) $raw;
    }

    private function readPrefilledVehicleId(Request $request, string $ownerId): ?string
    {
        $raw = $request->query->get('vehicle_id');
        if (!is_scalar($raw)) {
            return null;
        }

        $vehicleId = trim((string) $raw);
        if ('' === $vehicleId || !Uuid::isValid($vehicleId)) {
            return null;
        }

        return $this->vehicleRepository->belongsToOwner($vehicleId, $ownerId) ? $vehicleId : null;
    }

    private function readPrefilledStation(Request $request): ?\App\Station\Domain\Station
    {
        $raw = $request->query->get('station_id');
        if (!is_scalar($raw)) {
            return null;
        }

        $stationId = trim((string) $raw);
        if ('' === $stationId || !Uuid::isValid($stationId)) {
            return null;
        }

        return $this->stationRepository->get($stationId);
    }
}
