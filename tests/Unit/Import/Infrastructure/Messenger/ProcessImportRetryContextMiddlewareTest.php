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

namespace App\Tests\Unit\Import\Infrastructure\Messenger;

use App\Import\Application\Message\ProcessImportJobMessage;
use App\Import\Infrastructure\Messenger\ProcessImportRetryContextMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class ProcessImportRetryContextMiddlewareTest extends TestCase
{
    public function testItInjectsCurrentRedeliveryCountForImportMessages(): void
    {
        $middleware = new ProcessImportRetryContextMiddleware();
        $stack = new TestStack();

        $envelope = new Envelope(new ProcessImportJobMessage('019ce911-3982-786c-92b6-73efcfe467a9'), [
            new RedeliveryStamp(2),
        ]);

        $middleware->handle($envelope, $stack);

        self::assertInstanceOf(Envelope::class, $stack->spy->receivedEnvelope);
        $handlerArguments = $stack->spy->receivedEnvelope->last(HandlerArgumentsStamp::class);
        self::assertInstanceOf(HandlerArgumentsStamp::class, $handlerArguments);
        self::assertSame([2], $handlerArguments->getAdditionalArguments());
    }
}

final class TestStack implements StackInterface
{
    public TestSpyMiddleware $spy;

    public function __construct()
    {
        $this->spy = new TestSpyMiddleware();
    }

    public function next(): MiddlewareInterface
    {
        return $this->spy;
    }
}

final class TestSpyMiddleware implements MiddlewareInterface
{
    public ?Envelope $receivedEnvelope = null;

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->receivedEnvelope = $envelope;

        return $envelope;
    }
}
