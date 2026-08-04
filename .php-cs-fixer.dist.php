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
        'blank_line_after_opening_tag' => false,
        'declare_strict_types' => true,
        'linebreak_after_opening_tag' => false,
    ])
    ->setFinder($finder)
;
