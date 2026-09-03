<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/examples',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([
        '@PER-CS2x0' => true,
        '@PHP8x2Migration' => true,

        'array_syntax' => ['syntax' => 'short'],
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'declare_strict_types' => false,
        'modernize_strpos' => true,
        'modernize_types_casting' => true,
        'no_alias_functions' => true,
        'no_binary_string' => true,
        'no_unreachable_default_argument_value' => true,
        'no_unused_imports' => true,
        'no_useless_concat_operator' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_useless_sprintf' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_order' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'yoda_style' => false,

        // Keep calls and PHPDoc readable instead of forcing global qualification
        // or converting intentional API documentation into regular comments.
        'is_null' => false,
        'native_function_invocation' => false,
        'native_constant_invocation' => false,
        'phpdoc_to_comment' => false,
    ]);
