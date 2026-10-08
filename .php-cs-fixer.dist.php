<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'frontend'])
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        // Keep `/** @var list<X> */` before `return`: PHPStan reads it.
        'phpdoc_to_comment' => ['allow_before_return_statement' => true],
    ])
    ->setFinder($finder)
;
