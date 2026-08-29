<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/config');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        // Tradernet / FFTech PHP style (PSR-12 + PER clarifications).
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,

        'declare_strict_types' => true,
        'blank_line_after_opening_tag' => true,
        'single_blank_line_at_eof' => true,
        'linebreak_after_opening_tag' => true,

        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'no_unused_imports' => true,
        // All class FQCNs → use imports (TN request).
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => true,
        ],
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
            'leading_backslash_in_global_namespace' => false,
            'phpdoc_tags' => [
                'param',
                'phpstan-param',
                'phpstan-property',
                'phpstan-property-read',
                'phpstan-property-write',
                'phpstan-return',
                'phpstan-var',
                'property',
                'property-read',
                'property-write',
                'psalm-param',
                'psalm-property',
                'psalm-property-read',
                'psalm-property-write',
                'psalm-return',
                'psalm-var',
                'return',
                'throws',
                'var',
            ],
        ],

        'single_quote' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'concat_space' => ['spacing' => 'one'],
        'unary_operator_spaces' => true,
        'not_operator_with_successor_space' => false,

        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'declare',
                'return',
                'throw',
                'try',
            ],
        ],

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
                'property_public_static',
                'property_protected_static',
                'property_private_static',
                'property_public_readonly',
                'property_protected_readonly',
                'property_private_readonly',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_public_static',
                'method_protected',
                'method_protected_static',
                'method_private',
                'method_private_static',
            ],
            'sort_algorithm' => 'alpha',
        ],

        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'remove_inheritdoc' => false,
        ],

        'native_function_invocation' => false,
        'native_constant_invocation' => false,
        'static_lambda' => false,
        'strict_comparison' => true,
        'strict_param' => true,
        'mb_str_functions' => false,
        'final_class' => false,
        'final_internal_class' => false,
        'final_public_method_for_abstract_class' => false,

        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],

        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'return_assignment' => false,

        'visibility_required' => false,
        'modifier_keywords' => ['elements' => ['property', 'method', 'const']],
    ])
    ->setFinder($finder);
