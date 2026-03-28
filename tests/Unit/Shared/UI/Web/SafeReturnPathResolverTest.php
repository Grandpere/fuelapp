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

namespace App\Tests\Unit\Shared\UI\Web;

use App\Shared\UI\Web\SafeReturnPathResolver;
use PHPUnit\Framework\TestCase;

final class SafeReturnPathResolverTest extends TestCase
{
    public function testResolveKeepsInternalPath(): void
    {
        $resolver = new SafeReturnPathResolver();

        self::assertSame('/ui/receipts?view=compact', $resolver->resolve('/ui/receipts?view=compact', '/ui/dashboard'));
    }

    public function testResolveRejectsAbsoluteExternalUrl(): void
    {
        $resolver = new SafeReturnPathResolver();

        self::assertSame('/ui/dashboard', $resolver->resolve('https://evil.example/phish', '/ui/dashboard'));
    }

    public function testResolveRejectsProtocolRelativePath(): void
    {
        $resolver = new SafeReturnPathResolver();

        self::assertSame('/ui/dashboard', $resolver->resolve('//evil.example/phish', '/ui/dashboard'));
    }
}
