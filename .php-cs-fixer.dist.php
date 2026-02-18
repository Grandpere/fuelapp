<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$header =  <<<EOF
This file is part of a FuelApp project.

(c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

$finder = Finder::create()
    ->in(__DIR__ . '/migrations')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/public')
    ->exclude(['var', 'config', 'templates']);

$defaultRules = [
    '@Symfony' => true,
    '@PHP8x5Migration' => true,
    'array_indentation' => true,
    'array_syntax' => [
        'syntax' => 'short',
    ],
    'declare_strict_types' => true,
    'header_comment' => [
        'header' => $header,
        'comment_type' => 'comment',
    ],
    'global_namespace_import' => [
        'import_classes' => true,
        'import_functions' => false,
        'import_constants' => false,
    ],
    'single_blank_line_at_eof' => true,
    'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
    'php_unit_test_case_static_method_calls' => [
        'call_type' => 'self',
    ],
    'phpdoc_to_comment' => [
        'allow_before_return_statement' => true,
    ],
];

$customRules = [];
$mergedRules = array_merge($defaultRules, $customRules);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules($mergedRules)
    ->setFinder($finder);

