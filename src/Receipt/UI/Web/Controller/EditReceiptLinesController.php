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

use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Application\Command\UpdateReceiptLinesCommand;
use App\Receipt\Application\Command\UpdateReceiptLinesHandler;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Enum\FuelType;
use App\Security\Voter\ReceiptVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use ValueError;

final class EditReceiptLinesController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly UpdateReceiptLinesHandler $updateReceiptLinesHandler,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/ui/receipts/{id}/edit', name: 'ui_receipt_edit_lines', methods: ['GET', 'POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): Response
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(ReceiptVoter::VIEW, $id);

        $receipt = $this->receiptRepository->get($id);
        if (null === $receipt) {
            throw $this->createNotFoundException('Receipt not found.');
        }

        $formLines = [];
        foreach ($receipt->lines() as $line) {
            $formLines[] = [
                'fuelType' => $line->fuelType()->value,
                'quantityMilliLiters' => (string) $line->quantityMilliLiters(),
                'unitPriceDeciCentsPerLiter' => (string) $line->unitPriceDeciCentsPerLiter(),
                'vatRatePercent' => (string) $line->vatRatePercent(),
            ];
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $payload = $request->request->all('lines');
            $token = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('receipt_edit_lines_'.$id, $token)) {
                $errors[] = 'Invalid CSRF token.';
            }

            if ([] === $payload) {
                $errors[] = 'At least one line is required.';
            } else {
                $formLines = [];
                foreach ($payload as $rawLine) {
                    if (!is_array($rawLine)) {
                        continue;
                    }

                    $fuelType = $rawLine['fuelType'] ?? '';
                    $quantity = $rawLine['quantityMilliLiters'] ?? '';
                    $unitPrice = $rawLine['unitPriceDeciCentsPerLiter'] ?? '';
                    $vatRate = $rawLine['vatRatePercent'] ?? '';

                    $formLines[] = [
                        'fuelType' => is_scalar($fuelType) ? trim((string) $fuelType) : '',
                        'quantityMilliLiters' => is_scalar($quantity) ? trim((string) $quantity) : '',
                        'unitPriceDeciCentsPerLiter' => is_scalar($unitPrice) ? trim((string) $unitPrice) : '',
                        'vatRatePercent' => is_scalar($vatRate) ? trim((string) $vatRate) : '',
                    ];
                }
            }

            $lineCommands = [];
            if ([] === $errors) {
                [$lineCommands, $errors] = $this->buildLineCommands($formLines);
            }

            if ([] === $errors) {
                $updated = ($this->updateReceiptLinesHandler)(new UpdateReceiptLinesCommand(
                    $id,
                    $lineCommands,
                ));

                if (null === $updated) {
                    throw $this->createNotFoundException('Receipt not found.');
                }

                $this->addFlash('success', 'Receipt lines updated.');

                return new RedirectResponse($this->generateUrl('ui_receipt_show', ['id' => $id]), Response::HTTP_SEE_OTHER);
            }
        }

        $response = $this->render('receipt/edit_lines.html.twig', [
            'receipt' => $receipt,
            'formLines' => $formLines,
            'errors' => $errors,
            'fuelTypes' => array_map(static fn (FuelType $fuelType): string => $fuelType->value, FuelType::cases()),
            'csrfToken' => $this->csrfTokenManager->getToken('receipt_edit_lines_'.$id)->getValue(),
        ]);

        if ([] !== $errors) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /**
     * @param list<array{fuelType:string,quantityMilliLiters:string,unitPriceDeciCentsPerLiter:string,vatRatePercent:string}> $formLines
     *
     * @return array{0:list<CreateReceiptLineCommand>,1:list<string>}
     */
    private function buildLineCommands(array $formLines): array
    {
        $errors = [];
        $commands = [];

        foreach ($formLines as $index => $line) {
            try {
                $fuelType = FuelType::from($line['fuelType']);
            } catch (ValueError) {
                $errors[] = sprintf('Line %d: invalid fuel type.', $index + 1);
                continue;
            }

            $quantity = filter_var($line['quantityMilliLiters'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            $unitPrice = filter_var($line['unitPriceDeciCentsPerLiter'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            $vatRate = filter_var($line['vatRatePercent'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

            if (null === $quantity || $quantity <= 0) {
                $errors[] = sprintf('Line %d: quantity must be a positive integer.', $index + 1);
            }
            if (null === $unitPrice || $unitPrice < 0) {
                $errors[] = sprintf('Line %d: unit price must be an integer >= 0.', $index + 1);
            }
            if (null === $vatRate || $vatRate < 0 || $vatRate > 100) {
                $errors[] = sprintf('Line %d: VAT rate must be between 0 and 100.', $index + 1);
            }

            if (null !== $quantity && $quantity > 0 && null !== $unitPrice && $unitPrice >= 0 && null !== $vatRate && $vatRate >= 0 && $vatRate <= 100) {
                $commands[] = new CreateReceiptLineCommand($fuelType, $quantity, $unitPrice, $vatRate);
            }
        }

        if ([] === $commands) {
            $errors[] = 'At least one valid line is required.';
        }

        return [$commands, array_values(array_unique($errors))];
    }
}
