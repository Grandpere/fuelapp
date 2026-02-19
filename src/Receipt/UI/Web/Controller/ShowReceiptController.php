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

use App\Receipt\Application\Repository\ReceiptRepository;
use App\Station\Application\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowReceiptController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly StationRepository $stationRepository,
    ) {
    }

    #[Route('/ui/receipts/{id}', name: 'ui_receipt_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id): Response
    {
        $receipt = $this->receiptRepository->get($id);
        if (null === $receipt) {
            throw $this->createNotFoundException('Receipt not found.');
        }

        $station = null;
        if (null !== $receipt->stationId()) {
            $station = $this->stationRepository->get($receipt->stationId()->toString());
        }

        return $this->render('receipt/show.html.twig', [
            'receipt' => $receipt,
            'station' => $station,
        ]);
    }
}
