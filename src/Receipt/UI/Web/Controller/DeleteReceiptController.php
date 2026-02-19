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
use App\Receipt\UI\Realtime\ReceiptStreamPublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteReceiptController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly ReceiptStreamPublisher $streamPublisher,
    ) {
    }

    #[Route('/ui/receipts/{id}/delete', name: 'ui_receipt_delete', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete_receipt_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->receiptRepository->delete($id);
        $this->streamPublisher->publishDeleted($id);
        $this->addFlash('success', 'Receipt deleted.');

        $redirectUrl = (string) $request->request->get('_redirect', '');
        if (!str_starts_with($redirectUrl, '/') || str_starts_with($redirectUrl, '//')) {
            $redirectUrl = $this->generateUrl('ui_receipt_index');
        }

        return new RedirectResponse($redirectUrl, Response::HTTP_SEE_OTHER);
    }
}
