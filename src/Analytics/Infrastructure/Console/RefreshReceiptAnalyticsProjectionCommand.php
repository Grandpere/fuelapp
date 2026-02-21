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

namespace App\Analytics\Infrastructure\Console;

use App\Analytics\Application\Aggregation\ReceiptAnalyticsProjectionRefresher;
use App\Analytics\Application\Message\RefreshReceiptAnalyticsProjectionMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:analytics:receipts:refresh', description: 'Refresh receipt analytics read-model projection')]
final class RefreshReceiptAnalyticsProjectionCommand extends Command
{
    public function __construct(
        private readonly ReceiptAnalyticsProjectionRefresher $refresher,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('async', null, InputOption::VALUE_NONE, 'Dispatch refresh to messenger async transport');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (true === $input->getOption('async')) {
            $this->messageBus->dispatch(new RefreshReceiptAnalyticsProjectionMessage());
            $output->writeln('<info>Receipt analytics projection refresh message dispatched.</info>');

            return Command::SUCCESS;
        }

        $report = $this->refresher->refresh();

        $output->writeln(sprintf('<info>Receipt analytics projection refreshed at %s.</info>', $report->refreshedAt->format('Y-m-d H:i:s')));
        $output->writeln(sprintf('Rows materialized: %d', $report->rowsMaterialized));
        $output->writeln(sprintf('Source receipts: %d', $report->sourceReceiptCount));
        $output->writeln(sprintf(
            'Source max issuedAt: %s',
            $report->sourceMaxIssuedAt?->format('Y-m-d H:i:s') ?? 'n/a',
        ));

        return Command::SUCCESS;
    }
}
