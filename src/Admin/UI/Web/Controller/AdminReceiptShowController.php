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

use App\Receipt\Application\Repository\ReceiptRepository;
use App\Station\Application\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    #[Route('/ui/admin/receipts/{id}', name: 'ui_admin_receipt_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id): Response
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

        return $this->render('admin/receipts/show.html.twig', [
            'receipt' => $receipt,
            'station' => $station,
        ]);
    }
}
