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
use App\Import\Application\Command\DeleteImportJobCommand;
use App\Import\Application\Command\DeleteImportJobHandler;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminImportJobDeleteController extends AbstractController
{
    public function __construct(
        private readonly DeleteImportJobHandler $deleteImportJobHandler,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/ui/admin/imports/{id}/delete', name: 'ui_admin_import_job_delete', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['POST'])]
    public function __invoke(string $id, Request $request): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin_import_delete_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('ui_admin_import_job_show', ['id' => $id]);
        }

        try {
            ($this->deleteImportJobHandler)(new DeleteImportJobCommand($id));
            $this->auditTrail->record(
                'admin.import.delete.ui',
                'import_job',
                $id,
                [
                    'after' => ['deleted' => true],
                ],
            );
            $this->addFlash('success', 'Import file and job deleted.');

            return $this->redirectToRoute('ui_admin_import_job_list');
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('ui_admin_import_job_show', ['id' => $id]);
        }
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
