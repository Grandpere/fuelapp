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

class CleanArchitectureTest
{
    public function testDomainDoesNotDependOnAnyLayers(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace('/^App\\\\[a-zA-Z]+\\\\Domain/', true),
            )
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('/^App\\\\[a-zA-Z]+\\\\Application/', true),
                Selector::inNamespace('/^App\\\\[a-zA-Z]+\\\\Infrastructure/', true),
                Selector::inNamespace('/^App\\\\[a-zA-Z]+\\\\UI/', true),
            )
            ->because('Domain does not depend on Application, UI and Infrastructure layers.');
    }

    public function testApplicationDoesNotDependOnUpperLayers(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace('/^App\\\\[a-zA-Z]+\\\\Application/', true),
            )
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('/^App\\\\[a-zA-Z]+\\\\UI/', true),
                Selector::inNamespace('/^App\\\\[a-zA-Z]+\\\\Infrastructure/', true),
            )
            ->because('Application does not depend on UI and Infrastructure layers.');
    }

    public function testUIDoesNotDependOnInfrastructureLayers(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace('/^App\\\\[a-zA-Z]+\\\\UI/', true),
            )
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('/^App\\\\[a-zA-Z]+\\\\Infrastructure/', true),
            )
            ->because('UI does not depend on Infrastructure layer.');
    }
}
