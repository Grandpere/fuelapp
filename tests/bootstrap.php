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

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';
putenv('APP_ENV=test');

$_SERVER['APP_DEBUG'] = '1';
$_ENV['APP_DEBUG'] = '1';
putenv('APP_DEBUG=1');

if (method_exists(Dotenv::class, 'bootEnv')) {
    // Keep real env vars from Docker (DATABASE_URL, etc.), only force APP_ENV=test.
    new Dotenv()->bootEnv(dirname(__DIR__).'/.env', 'test', ['test'], false);
}

$_SERVER['KERNEL_CLASS'] ??= App\Kernel::class;
$_ENV['KERNEL_CLASS'] ??= App\Kernel::class;

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}
