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
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminImportJobShowController extends AbstractController
{
    public function __construct(private readonly ImportJobRepository $importJobRepository)
    {
    }

    #[Route('/ui/admin/imports/{id}', name: 'ui_admin_import_job_show', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $job = $this->importJobRepository->getForSystem($id);
        if (null === $job) {
            throw new NotFoundHttpException();
        }

        return $this->render('admin/imports/show.html.twig', [
            'job' => $job,
            'payload' => $job->errorPayload(),
            'payloadData' => $this->decodePayload($job->errorPayload()),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function decodePayload(?string $payload): ?array
    {
        if (null === $payload || '' === trim($payload)) {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
