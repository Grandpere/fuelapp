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

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Application\Review\ImportJobPayloadReparser;
use App\Shared\UI\Web\SafeReturnPathResolver;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ImportJobReparseWebController extends AbstractController
{
    public function __construct(
        private readonly ImportJobRepository $importJobRepository,
        private readonly ImportJobPayloadReparser $importJobPayloadReparser,
        private readonly SafeReturnPathResolver $safeReturnPathResolver,
    ) {
    }

    #[Route('/ui/imports/{id}/reparse', name: 'ui_import_reparse', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['POST'])]
    public function __invoke(string $id, Request $request): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $returnTo = $this->safeReturnPathResolver->resolve(
            $request->request->get('_return_to'),
            $this->generateUrl('ui_import_index'),
        );

        if (!$this->isCsrfTokenValid('ui_import_reparse_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('ui_import_show', ['id' => $id, 'return_to' => $returnTo]);
        }

        $job = $this->importJobRepository->get($id);
        if (null === $job) {
            throw $this->createNotFoundException();
        }

        try {
            $this->importJobPayloadReparser->reparse($job);
            $this->importJobRepository->save($job);
            $this->addFlash('success', 'Import payload reparsed with current parser rules.');
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('ui_import_show', ['id' => $id, 'return_to' => $returnTo]);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
