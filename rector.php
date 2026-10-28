<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\CodeQuality\Rector\Class_\EventSubscriberToAttributeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths(array_values(array_filter([
        is_dir(__DIR__.'/src') ? __DIR__.'/src' : null,
        is_dir(__DIR__.'/tests') ? __DIR__.'/tests' : null,
    ])))
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ])
    ->withImportNames()
    // Keep the existing subscriber interface and getSubscribedEvents() API in 6.x.
    ->withSkip(class_exists(EventSubscriberToAttributeRector::class) ? [
        EventSubscriberToAttributeRector::class => [__DIR__.'/src/EventListener/MediaTypeSearchFilterListener.php'],
    ] : [])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withComposerBased(symfony: true);
