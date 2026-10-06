<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php83: true)
    ->withTypeCoverageLevel(0)
    ->withSkip([
        // Symfony appelle choice_label avec trois arguments : CountryList::from(...), méthode native,
        // lèverait une ArgumentCountError là où la fonction fléchée ignore les arguments en trop
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class => [
            __DIR__.'/src/Form/Checkout/CheckoutAddressType.php',
        ],
    ]);
