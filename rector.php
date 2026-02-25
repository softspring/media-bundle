<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Symfony61\Rector\Class_\CommandConfigureToAttributeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths(array_values(array_filter([
        is_dir(__DIR__.'/src') ? __DIR__.'/src' : null,
        is_dir(__DIR__.'/tests') ? __DIR__.'/tests' : null,
    ])))
    ->withSets([
        SymfonySetList::SYMFONY_80,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withComposerBased(symfony: true)
    ->withSkip([
        CommandConfigureToAttributeRector::class,
    ]);
