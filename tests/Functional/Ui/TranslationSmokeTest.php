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

namespace App\Tests\Functional\Ui;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslationSmokeTest extends KernelTestCase
{
    public function testFrenchIsDefaultLocaleAndEnglishTranslationsExist(): void
    {
        self::bootKernel();

        $translator = static::getContainer()->get(TranslatorInterface::class);

        self::assertInstanceOf(TranslatorInterface::class, $translator);
        self::assertSame('fr', $translator->getLocale());
        self::assertSame('Tableau de bord', $translator->trans('nav.dashboard', locale: 'fr'));
        self::assertSame('Dashboard', $translator->trans('nav.dashboard', locale: 'en'));
    }
}
