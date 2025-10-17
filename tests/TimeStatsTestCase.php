<?php

namespace Spatie\Stats\Tests;

use CreateTimeStatsTables;
use Illuminate\Database\Schema\Blueprint;

class TimeStatsTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setupTimeStatsDatabase();
    }

    public function setupTimeStatsDatabase()
    {
        include_once __DIR__.'/../database/migrations/create_time_stats_tables.php.stub';

        (new CreateTimeStatsTables())->up();

        // Add tenant_id and employee_id columns for testing multi-tenant scenarios
        $this->app['db']->connection()->getSchemaBuilder()->table('time_stats_events', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->unsignedBigInteger('employee_id')->nullable()->after('tenant_id');
        });
    }
}
