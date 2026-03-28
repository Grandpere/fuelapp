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
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminReceiptShowController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly StationRepository $stationRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly ImportJobRepository $importJobRepository,
    ) {
    }

    #[Route('/ui/admin/receipts/{id}', name: 'ui_admin_receipt_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $receipt = $this->receiptRepository->getForSystem($id);
        if (null === $receipt) {
            throw new NotFoundHttpException();
        }

        $station = null;
        if (null !== $receipt->stationId()) {
            $station = $this->stationRepository->getForSystem($receipt->stationId()->toString());
        }

        $vehicle = null;
        if (null !== $receipt->vehicleId()) {
            $vehicle = $this->vehicleRepository->get($receipt->vehicleId()->toString());
        }

        $requestedReturnTo = $request->query->get('return_to');
        $backToListUrl = is_string($requestedReturnTo) && '' !== trim($requestedReturnTo) && str_starts_with($requestedReturnTo, '/') && !str_starts_with($requestedReturnTo, '//')
            ? $requestedReturnTo
            : $this->generateUrl('ui_admin_receipt_list');

        return $this->render('admin/receipts/show.html.twig', [
            'receipt' => $receipt,
            'station' => $station,
            'vehicle' => $vehicle,
            'backToListUrl' => $backToListUrl,
            'relatedImports' => $this->findRelatedImports($receipt->id()->toString(), $backToListUrl),
            'supportShortcuts' => $this->buildSupportShortcuts($receipt->id()->toString(), $vehicle?->id()?->toString(), $station?->id()?->toString(), $backToListUrl),
        ]);
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
     * @return list<array{label:string,url:string}>
     */
    private function buildSupportShortcuts(string $receiptId, ?string $vehicleId, ?string $stationId, string $returnTo): array
    {
        $shortcuts = [];

        if (null !== $vehicleId) {
            $shortcuts[] = [
                'label' => 'Open vehicle',
                'url' => $this->generateUrl('ui_admin_vehicle_show', ['id' => $vehicleId, 'return_to' => $returnTo]),
            ];
            $shortcuts[] = [
                'label' => 'Vehicle receipts',
                'url' => $this->generateUrl('ui_admin_receipt_list', ['vehicle_id' => $vehicleId]),
            ];
        }

        if (null !== $stationId) {
            $shortcuts[] = [
                'label' => 'Open station',
                'url' => $this->generateUrl('ui_admin_station_show', ['id' => $stationId, 'return_to' => $returnTo]),
            ];
            $shortcuts[] = [
                'label' => 'Station receipts',
                'url' => $this->generateUrl('ui_admin_receipt_list', ['station_id' => $stationId]),
            ];
        }

        foreach ($this->findRelatedImports($receiptId, $returnTo) as $item) {
            $shortcuts[] = [
                'label' => 'Open related import',
                'url' => $item['url'],
            ];
            break;
        }

        return $shortcuts;
    }
}
