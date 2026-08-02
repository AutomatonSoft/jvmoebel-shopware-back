<?php declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->files()
    ->name('*.php')
    ->in(__DIR__.'/custom/static-plugins')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder)
;
