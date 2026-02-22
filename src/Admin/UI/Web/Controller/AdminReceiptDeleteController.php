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
use App\Receipt\Application\Repository\ReceiptRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminReceiptDeleteController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/ui/admin/receipts/{id}/delete', name: 'ui_admin_receipt_delete', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin_receipt_delete_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('ui_admin_receipt_show', ['id' => $id]);
        }

        $receipt = $this->receiptRepository->getForSystem($id);
        if (null === $receipt) {
            throw $this->createNotFoundException();
        }

        $this->receiptRepository->deleteForSystem($id);
        $this->auditTrail->record(
            'admin.receipt.deleted.ui',
            'receipt',
            $id,
            ['after' => ['deleted' => true]],
        );
        $this->addFlash('success', 'Receipt deleted.');

        return $this->redirectToRoute('ui_admin_receipt_list');
    }
}
