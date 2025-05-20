<?php

declare(strict_types=1);

use Rector\Config\Level\TypeDeclarationLevel;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelSetList;
use App\Rector\AddActivityLogToModels;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        // __DIR__.'/bootstrap',
        // __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/public',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
        __DIR__.'/app/Models'
    ])
    // uncomment to reach your current PHP version
    ->withPhpSets()
    ->withSets([
        // SetList::PHP_82,
        SetList::PHP_83,
        LaravelSetList::LARAVEL_110,
        LaravelSetList::LARAVEL_IF_HELPERS,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
    ])
    // Type coverage levels from 0 to 47 (at the time of writing this config)
    // So, used count to get highest level
    ->withTypeCoverageLevel(46)
    ->withRules([
        AddActivityLogToModels::class
    ])->withSkip([
        // Skip property_exists conversion completely
        IssetOnPropertyObjectToPropertyExistsRector::class,
    ])
    ->withImportNames();
