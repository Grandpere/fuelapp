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

use App\Import\Application\Command\RetryImportJobCommand;
use App\Import\Application\Command\RetryImportJobHandler;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminImportJobRetryController extends AbstractController
{
    public function __construct(private readonly RetryImportJobHandler $retryImportJobHandler)
    {
    }

    #[Route('/ui/admin/imports/{id}/retry', name: 'ui_admin_import_job_retry', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['POST'])]
    public function __invoke(string $id, Request $request): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin_import_retry_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('ui_admin_import_job_show', ['id' => $id]);
        }

        try {
            ($this->retryImportJobHandler)(new RetryImportJobCommand($id));
            $this->addFlash('success', 'Import job queued for retry.');
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('ui_admin_import_job_show', ['id' => $id]);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
