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

namespace App\User\Infrastructure\Console;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:user:create', description: 'Create a local user account')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('password', InputArgument::REQUIRED, 'User password')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Add ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $emailArgument = $input->getArgument('email');
        $passwordArgument = $input->getArgument('password');
        if (!is_string($emailArgument) || !is_string($passwordArgument)) {
            $output->writeln('<error>Invalid command arguments.</error>');

            return Command::INVALID;
        }

        $email = mb_strtolower(trim($emailArgument));
        $password = $passwordArgument;

        $existing = $this->em->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        if (null !== $existing) {
            $output->writeln(sprintf('<error>User with email "%s" already exists.</error>', $email));

            return Command::FAILURE;
        }

        $roles = ['ROLE_USER'];
        if ((bool) $input->getOption('admin')) {
            $roles[] = 'ROLE_ADMIN';
        }

        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln(sprintf('<info>User created: %s</info>', $email));

        return Command::SUCCESS;
    }
}
