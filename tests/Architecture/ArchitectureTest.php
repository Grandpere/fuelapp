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
use PHPat\Test\PHPat;
use PHPat\Test\Rule;

final class ArchitectureTest
{
    public function test_app_does_not_depend_on_tests(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App'))
            ->shouldNotDependOn()
            ->classes(Selector::inNamespace('App\\Tests'))
            ->because('production code should not depend on test code');
    }
}
