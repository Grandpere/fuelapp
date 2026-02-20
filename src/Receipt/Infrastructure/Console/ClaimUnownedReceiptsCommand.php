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

namespace App\Receipt\Infrastructure\Console;

use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:receipts:claim-unowned', description: 'Assign unowned receipts to a user by email')]
final class ClaimUnownedReceiptsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Target user email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $emailArg = $input->getArgument('email');
        if (!is_string($emailArg) || '' === trim($emailArg)) {
            $output->writeln('<error>Email argument is required.</error>');

            return Command::INVALID;
        }

        $email = mb_strtolower(trim($emailArg));
        /** @var UserEntity|null $user */
        $user = $this->em->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        if (null === $user) {
            $output->writeln(sprintf('<error>User not found: %s</error>', $email));

            return Command::FAILURE;
        }

        $updatedRaw = $this->em->createQueryBuilder()
            ->update(ReceiptEntity::class, 'r')
            ->set('r.owner', ':owner')
            ->where('r.owner IS NULL')
            ->setParameter('owner', $user)
            ->getQuery()
            ->execute();
        $updated = is_int($updatedRaw) ? $updatedRaw : 0;

        $output->writeln(sprintf('<info>%d unowned receipt(s) assigned to %s</info>', $updated, $email));

        return Command::SUCCESS;
    }
}
