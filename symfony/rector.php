<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;
use Rector\Transform\ValueObject\AttributeKeyToClassConstFetch;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/assets',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
    ])
    ->withPhpSets()
    ->withPreparedSets(typeDeclarations: true)
    ->withPreparedSets(codeQuality: true)
    ->withSets([SetList::PHP_84])
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withConfiguredRule(AttributeKeyToClassConstFetchRector::class, [
        new AttributeKeyToClassConstFetch('Doctrine\\ORM\\Mapping\\Column', 'type', 'Doctrine\\DBAL\\Types\\Types', [
            'string' => 'STRING',
            'integer' => 'INTEGER',
            'json' => 'JSON',
            'boolean' => 'BOOLEAN',
    ]),
    ])
    ->withImportNames(importShortClasses: false)
    ->withPreparedSets(deadCode: true);
