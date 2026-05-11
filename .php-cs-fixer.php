<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/migrations')
    ->name('*.php')
;

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        // Base rulesets
        '@Symfony'             => true,
        '@Symfony:risky'       => true,
        '@PHP84Migration'      => true,
        '@PHP80Migration:risky' => true,

        // Strict types on every file
        'declare_strict_types' => true,

        // Imports
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order'  => ['class', 'function', 'const'],
        ],
        'global_namespace_import' => [
            'import_classes'   => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'no_unused_imports' => true,
        'fully_qualified_strict_types' => true,

        // Class structure
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'phpunit',
                'method_public_static',
                'method_public_abstract',
                'method_public',
                'method_protected_static',
                'method_protected_abstract',
                'method_protected',
                'method_private_static',
                'method_private',
                'magic',
            ],
            'sort_algorithm' => 'none',
        ],
        'ordered_interfaces' => true,

        // PHPDoc
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed'         => true,
            'remove_inheritdoc'   => true,
            'allow_unused_params' => false,
        ],
        'phpdoc_align'          => ['align' => 'left'],
        'phpdoc_order'          => true,
        'phpdoc_separation'     => true,
        'phpdoc_trim'           => true,
        'phpdoc_var_annotation_correct_order' => true,

        // Arrays and trailing commas
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],
        'array_syntax' => ['syntax' => 'short'],

        // Operators and casts
        'concat_space'     => ['spacing' => 'one'],
        'binary_operator_spaces' => [
            'default'   => 'single_space',
            'operators' => ['=>' => 'align_single_space_minimal'],
        ],
        'cast_spaces' => ['space' => 'single'],

        // Control structures
        'no_alternative_syntax' => true,
        'simplified_if_return'  => true,

        // Native function/constant invocations (perf + clarity)
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope'   => 'namespaced',
            'strict'  => true,
        ],
        'native_constant_invocation' => [
            'fix_built_in' => true,
            'include'      => [],
            'scope'        => 'namespaced',
            'strict'       => true,
        ],

        // Misc cleanup
        'no_useless_else'           => true,
        'no_useless_return'         => true,
        'return_assignment'         => true,
        'single_line_throw'         => false,
        'yoda_style'                => false,
        'increment_style'           => ['style' => 'post'],
        'modernize_strpos'          => true,
        'get_class_to_class_keyword' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
;
