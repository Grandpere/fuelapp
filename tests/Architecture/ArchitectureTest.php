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

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ArchitectureTest
{
    public function testAppDoesNotDependOnTests(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^App\\\\(?!Tests\\\\)/', true))
            ->shouldNotDependOn()
            ->classes(Selector::inNamespace('App\\Tests'))
            ->because('production code should not depend on test code');
    }
}
