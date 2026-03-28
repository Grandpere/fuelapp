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

use App\Admin\Application\Audit\AdminAuditTrail;
use App\Import\Application\Repository\ImportJobRepository;
use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Application\Command\UpdateReceiptLinesCommand;
use App\Receipt\Application\Command\UpdateReceiptLinesHandler;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\Domain\Receipt;
use App\Shared\UI\Web\SafeReturnPathResolver;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use ValueError;

final class AdminReceiptFormController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly UpdateReceiptLinesHandler $updateReceiptLinesHandler,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly AdminAuditTrail $auditTrail,
        private readonly SafeReturnPathResolver $safeReturnPathResolver,
        private readonly VehicleRepository $vehicleRepository,
        private readonly StationRepository $stationRepository,
        private readonly ImportJobRepository $importJobRepository,
    ) {
    }

    #[Route('/ui/admin/receipts/{id}/edit', name: 'ui_admin_receipt_edit', methods: ['GET', 'POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $receipt = $this->receiptRepository->getForSystem($id);
        if (null === $receipt) {
            throw new NotFoundHttpException();
        }

        $backToReceiptUrl = $this->safeReturnPathResolver->resolve(
            $request->query->get('return_to') ?? $request->request->get('_return_to'),
            $this->generateUrl('ui_admin_receipt_show', ['id' => $id]),
        );
        $vehicleId = $receipt->vehicleId()?->toString();
        $stationId = $receipt->stationId()?->toString();
        $vehicle = null !== $vehicleId ? $this->vehicleRepository->get($vehicleId) : null;
        $station = null !== $stationId ? $this->stationRepository->getForSystem($stationId) : null;
        $relatedImports = $this->findRelatedImports($id, $backToReceiptUrl);

        $formLines = $this->receiptToFormLines($receipt);
        $errors = [];

        if ($request->isMethod('POST')) {
            $rawLines = $request->request->all('lines');
            $token = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('admin_receipt_form_'.$id, $token)) {
                $errors[] = 'Invalid CSRF token.';
            }

            if ([] === $rawLines) {
                $errors[] = 'At least one line is required.';
            } else {
                $formLines = [];
                foreach ($rawLines as $rawLine) {
                    if (!is_array($rawLine)) {
                        continue;
                    }

                    $fuelType = $rawLine['fuelType'] ?? '';
                    $quantity = $rawLine['quantityMilliLiters'] ?? '';
                    $unitPrice = $rawLine['unitPriceDeciCentsPerLiter'] ?? '';
                    $vatRate = $rawLine['vatRatePercent'] ?? '';

                    $formLines[] = [
                        'fuelType' => is_scalar($fuelType) ? trim((string) $fuelType) : '',
                        'quantityMilliLiters' => is_scalar($quantity) ? trim((string) $quantity) : '',
                        'unitPriceDeciCentsPerLiter' => is_scalar($unitPrice) ? trim((string) $unitPrice) : '',
                        'vatRatePercent' => is_scalar($vatRate) ? trim((string) $vatRate) : '',
                    ];
                }
            }

            $lineCommands = [];
            if ([] === $errors) {
                [$lineCommands, $errors] = $this->buildLineCommands($formLines);
            }

            if ([] === $errors) {
                $before = $this->receiptSnapshot($receipt);

                $updated = ($this->updateReceiptLinesHandler)(new UpdateReceiptLinesCommand(
                    $id,
                    $lineCommands,
                    true,
                ));
                if (null === $updated) {
                    throw new NotFoundHttpException();
                }

                $this->auditTrail->record(
                    'admin.receipt.updated.ui',
                    'receipt',
                    $id,
                    [
                        'before' => $before,
                        'after' => $this->receiptSnapshot($updated),
                    ],
                );

                $this->addFlash('success', 'Receipt updated.');

                return new RedirectResponse($backToReceiptUrl, Response::HTTP_SEE_OTHER);
            }
        }

        $response = $this->render('admin/receipts/form.html.twig', [
            'receipt' => $receipt,
            'formLines' => $formLines,
            'errors' => $errors,
            'fuelTypes' => array_map(static fn (FuelType $fuelType): string => $fuelType->value, FuelType::cases()),
            'csrfToken' => $this->csrfTokenManager->getToken('admin_receipt_form_'.$id)->getValue(),
            'backToReceiptUrl' => $backToReceiptUrl,
            'vehicle' => $vehicle,
            'station' => $station,
            'relatedImports' => $relatedImports,
            'supportShortcuts' => $this->buildSupportShortcuts($vehicleId, $stationId, $backToReceiptUrl, $relatedImports),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /**
     * @param list<array{fuelType:string,quantityMilliLiters:string,unitPriceDeciCentsPerLiter:string,vatRatePercent:string}> $formLines
     *
     * @return array{0:list<CreateReceiptLineCommand>,1:list<string>}
     */
    private function buildLineCommands(array $formLines): array
    {
        $errors = [];
        $commands = [];

        foreach ($formLines as $index => $line) {
            try {
                $fuelType = FuelType::from($line['fuelType']);
            } catch (ValueError) {
                $errors[] = sprintf('Line %d: invalid fuel type.', $index + 1);
                continue;
            }

            $quantity = filter_var($line['quantityMilliLiters'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            $unitPrice = filter_var($line['unitPriceDeciCentsPerLiter'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            $vatRate = filter_var($line['vatRatePercent'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

            if (null === $quantity || $quantity <= 0) {
                $errors[] = sprintf('Line %d: quantity must be a positive integer.', $index + 1);
            }
            if (null === $unitPrice || $unitPrice < 0) {
                $errors[] = sprintf('Line %d: unit price must be an integer >= 0.', $index + 1);
            }
            if (null === $vatRate || $vatRate < 0 || $vatRate > 100) {
                $errors[] = sprintf('Line %d: VAT rate must be between 0 and 100.', $index + 1);
            }

            if (null !== $quantity && $quantity > 0 && null !== $unitPrice && $unitPrice >= 0 && null !== $vatRate && $vatRate >= 0 && $vatRate <= 100) {
                $commands[] = new CreateReceiptLineCommand($fuelType, $quantity, $unitPrice, $vatRate);
            }
        }

        if ([] === $commands) {
            $errors[] = 'At least one valid line is required.';
        }

        return [$commands, array_values(array_unique($errors))];
    }

    /**
     * @return list<array{fuelType:string,quantityMilliLiters:string,unitPriceDeciCentsPerLiter:string,vatRatePercent:string}>
     */
    private function receiptToFormLines(Receipt $receipt): array
    {
        $formLines = [];
        foreach ($receipt->lines() as $line) {
            $formLines[] = [
                'fuelType' => $line->fuelType()->value,
                'quantityMilliLiters' => (string) $line->quantityMilliLiters(),
                'unitPriceDeciCentsPerLiter' => (string) $line->unitPriceDeciCentsPerLiter(),
                'vatRatePercent' => (string) $line->vatRatePercent(),
            ];
        }

        return $formLines;
    }

    /**
     * @return array{
     *     issuedAt: string,
     *     totalCents: int,
     *     vatAmountCents: int,
     *     lines: list<array{fuelType: string, quantityMilliLiters: int, unitPriceDeciCentsPerLiter: int, vatRatePercent: int}>
     * }
     */
    private function receiptSnapshot(Receipt $receipt): array
    {
        $lines = [];
        foreach ($receipt->lines() as $line) {
            $lines[] = [
                'fuelType' => $line->fuelType()->value,
                'quantityMilliLiters' => $line->quantityMilliLiters(),
                'unitPriceDeciCentsPerLiter' => $line->unitPriceDeciCentsPerLiter(),
                'vatRatePercent' => $line->vatRatePercent(),
            ];
        }

        return [
            'issuedAt' => $receipt->issuedAt()->format(DATE_ATOM),
            'totalCents' => $receipt->totalCents(),
            'vatAmountCents' => $receipt->vatAmountCents(),
            'lines' => $lines,
        ];
    }

    /**
     * @return list<array{jobId:string,status:string,filename:string,url:string}>
     */
    private function findRelatedImports(string $receiptId, string $returnTo): array
    {
        $matches = [];

        foreach ($this->importJobRepository->allForSystem() as $job) {
            $payload = $job->errorPayload();
            if (null === $payload || '' === trim($payload)) {
                continue;
            }

            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                continue;
            }

            $finalizedReceiptId = $decoded['finalizedReceiptId'] ?? null;
            $duplicateOfReceiptId = $decoded['duplicateOfReceiptId'] ?? null;
            if ($finalizedReceiptId !== $receiptId && $duplicateOfReceiptId !== $receiptId) {
                continue;
            }

            $matches[] = [
                'jobId' => $job->id()->toString(),
                'status' => $job->status()->value,
                'filename' => $job->originalFilename(),
                'url' => $this->generateUrl('ui_admin_import_job_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]),
            ];
        }

        usort(
            $matches,
            static fn (array $left, array $right): int => strcmp($right['jobId'], $left['jobId']),
        );

        return $matches;
    }

    /**
     * @param list<array{jobId:string,status:string,filename:string,url:string}> $relatedImports
     *
     * @return list<array{label:string,url:string}>
     */
    private function buildSupportShortcuts(?string $vehicleId, ?string $stationId, string $backToReceiptUrl, array $relatedImports): array
    {
        $shortcuts = [[
            'label' => 'Back to receipt',
            'url' => $backToReceiptUrl,
        ]];

        if (null !== $vehicleId) {
            $shortcuts[] = [
                'label' => 'Open vehicle',
                'url' => $this->generateUrl('ui_admin_vehicle_show', ['id' => $vehicleId, 'return_to' => $backToReceiptUrl]),
            ];
            $shortcuts[] = [
                'label' => 'Vehicle receipts',
                'url' => $this->generateUrl('ui_admin_receipt_list', ['vehicle_id' => $vehicleId]),
            ];
        }

        if (null !== $stationId) {
            $shortcuts[] = [
                'label' => 'Open station',
                'url' => $this->generateUrl('ui_admin_station_show', ['id' => $stationId, 'return_to' => $backToReceiptUrl]),
            ];
            $shortcuts[] = [
                'label' => 'Station receipts',
                'url' => $this->generateUrl('ui_admin_receipt_list', ['station_id' => $stationId]),
            ];
        }

        foreach ($relatedImports as $relatedImport) {
            $shortcuts[] = [
                'label' => 'Open related import',
                'url' => $relatedImport['url'],
            ];
            break;
        }

        return $shortcuts;
    }
}
