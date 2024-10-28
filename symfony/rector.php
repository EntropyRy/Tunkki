<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/assets',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
    ])
    ->withPhpSets()
    ->withTypeCoverageLevel(0)
    ->withSets([SetList::PHP_83])
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withPreparedSets(deadCode: true);
