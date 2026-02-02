<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths(array_merge(
is_dir(__DIR__ . '/src') ? [__DIR__ . '/src'] : [],
        is_dir(__DIR__ . '/tests') ? [__DIR__ . '/tests'] : []
    ))
    ->withSets([
        SymfonySetList::SYMFONY_80,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withComposerBased(symfony: true);