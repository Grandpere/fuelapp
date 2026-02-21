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

namespace App\Tests\Integration\Import\Application;

use App\Import\Application\Command\CreateImportJobCommand;
use App\Import\Application\Command\CreateImportJobHandler;
use App\Import\Application\Message\ProcessImportJobMessage;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class ImportMessageDispatchTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CreateImportJobHandler $createImportJobHandler;
    private InMemoryTransport $asyncTransport;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $handler = self::getContainer()->get(CreateImportJobHandler::class);
        if (!$handler instanceof CreateImportJobHandler) {
            throw new RuntimeException('CreateImportJobHandler service not found.');
        }
        $this->createImportJobHandler = $handler;

        $transport = self::getContainer()->get('messenger.transport.async');
        if (!$transport instanceof InMemoryTransport) {
            throw new RuntimeException('Async transport is not in-memory in test env.');
        }
        $this->asyncTransport = $transport;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
        $this->asyncTransport->reset();
    }

    public function testCreateImportJobDispatchesProcessMessage(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail('import.dispatch@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('test1234');
        $this->em->persist($user);
        $this->em->flush();

        $source = sys_get_temp_dir().'/fuelapp-import-dispatch-'.uniqid('', true);
        file_put_contents($source, 'fake upload');

        $job = ($this->createImportJobHandler)(new CreateImportJobCommand(
            $user->getId()->toRfc4122(),
            $source,
            'receipt.pdf',
        ));

        $sent = $this->asyncTransport->getSent();
        self::assertCount(1, $sent);

        $message = $sent[0]->getMessage();
        self::assertInstanceOf(ProcessImportJobMessage::class, $message);
        self::assertSame($job->id()->toString(), $message->importJobId);
    }
}
