<?php

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('tests')
;

return new PhpCsFixer\Config()
    ->setRules([
        '@Symfony' => true,
        // Désactiver la conversion des @var en commentaires simples
        'phpdoc_to_comment' => false,
    ])
    ->setFinder($finder)
;
