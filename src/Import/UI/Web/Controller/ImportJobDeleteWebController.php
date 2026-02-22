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

namespace App\Import\UI\Web\Controller;

use App\Import\Application\Command\DeleteImportJobCommand;
use App\Import\Application\Command\DeleteImportJobHandler;
use App\Import\Application\Repository\ImportJobRepository;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ImportJobDeleteWebController extends AbstractController
{
    public function __construct(
        private readonly ImportJobRepository $importJobRepository,
        private readonly DeleteImportJobHandler $deleteImportJobHandler,
    ) {
    }

    #[Route('/ui/imports/{id}/delete', name: 'ui_import_delete', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['POST'])]
    public function __invoke(string $id, Request $request): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('ui_import_delete_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('ui_import_index');
        }

        if (null === $this->importJobRepository->get($id)) {
            throw $this->createNotFoundException();
        }

        try {
            ($this->deleteImportJobHandler)(new DeleteImportJobCommand($id));
            $this->addFlash('success', 'Import deleted.');
        } catch (InvalidArgumentException) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('ui_import_index');
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
