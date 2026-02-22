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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminReceiptListController extends AbstractController
{
    public function __construct(private readonly ReceiptRepository $receiptRepository)
    {
    }

    #[Route('/ui/admin/receipts', name: 'ui_admin_receipt_list', methods: ['GET'])]
    public function __invoke(): Response
    {
        $receipts = [];
        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            $receipts[] = $receipt;
        }

        return $this->render('admin/receipts/index.html.twig', [
            'receipts' => $receipts,
        ]);
    }
}
