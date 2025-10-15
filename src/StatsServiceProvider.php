<?php

namespace Spatie\Stats;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Stats\Console\MakeStatsCommand;

class StatsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('stats')
            ->hasMigration('create_stats_tables')
            ->hasCommand(MakeStatsCommand::class);
    }
}
