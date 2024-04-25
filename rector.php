<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Set\SensiolabsSetList;

return RectorConfig::configure()
    ->withImportNames(removeUnusedImports: true)
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
//        SensiolabsSetList::ANNOTATIONS_TO_ATTRIBUTES,
//        SymfonySetList::SYMFONY_62,
//        SymfonySetList::SYMFONY_CODE_QUALITY,
//        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ]);