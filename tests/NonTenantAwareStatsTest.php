<?php

namespace Spatie\Stats\Tests;

use BadMethodCallException;
use Carbon\Carbon;
use Spatie\Stats\Tests\TestClasses\Models\Tenant;
use Spatie\Stats\Tests\TestClasses\Models\TenantStat;

class NonTenantAwareStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2020-01-01');

        // Create tenant_stats table (NOT tenant-aware - no separate tenant_id column)
        $this->app['db']->connection()->getSchemaBuilder()->create('tenant_stats', function ($table) {
            $table->id();
            $table->foreignId('tenant_id'); // This is the SUBJECT, not a scope
            $table->string('name');
            $table->string('type');
            $table->bigInteger('value');
            $table->timestamps();
        });

        // Create tenants table
        $this->app['db']->connection()->getSchemaBuilder()->create('tenants', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /** @test */
    public function it_can_track_tenant_stats()
    {
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        TenantStat::for($tenant)->increase('active_users');

        $this->assertDatabaseHas('tenant_stats', [
            'tenant_id' => $tenant->id,
            'name' => 'active_users',
            'value' => 1,
            'type' => 'change',
        ]);
    }

    /** @test */
    public function it_can_set_tenant_stats()
    {
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        TenantStat::for($tenant)->set('storage_mb', 1024);

        $this->assertDatabaseHas('tenant_stats', [
            'tenant_id' => $tenant->id,
            'name' => 'storage_mb',
            'value' => 1024,
            'type' => 'set',
        ]);
    }

    /** @test */
    public function it_can_decrease_tenant_stats()
    {
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        TenantStat::for($tenant)->decrease('available_credits', 50);

        $this->assertDatabaseHas('tenant_stats', [
            'tenant_id' => $tenant->id,
            'name' => 'available_credits',
            'value' => -50,
            'type' => 'change',
        ]);
    }

    /** @test */
    public function it_throws_exception_when_calling_on_for_non_tenant_aware_stats()
    {
        $tenant = Tenant::create(['name' => 'Acme Corp']);
        $anotherTenant = Tenant::create(['name' => 'Another Corp']);

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('on() can only be called on tenant-aware stat models');

        TenantStat::for($tenant)->on($anotherTenant)->increase('users');
    }

    /** @test */
    public function it_can_track_multiple_stat_types_for_same_tenant()
    {
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        TenantStat::for($tenant)->increase('active_users');
        TenantStat::for($tenant)->increase('api_calls');
        TenantStat::for($tenant)->set('storage_mb', 2048);

        $this->assertEquals(3, TenantStat::where('tenant_id', $tenant->id)->count());
    }

    /** @test */
    public function it_can_query_tenant_stats()
    {
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        TenantStat::for($tenant)->set('active_users', 100, now()->subDays(5));
        TenantStat::for($tenant)->increase('active_users', 10, now()->subDays(2));
        TenantStat::for($tenant)->increase('active_users', 5, now());

        $stats = TenantStat::for($tenant)
            ->query('active_users')
            ->start(now()->subWeek())
            ->end(now())
            ->groupByDay()
            ->get();

        $this->assertNotEmpty($stats);
    }

    /** @test */
    public function it_can_track_stats_for_multiple_tenants_independently()
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);

        TenantStat::for($tenant1)->set('active_users', 50);
        TenantStat::for($tenant1)->increase('active_users', 10);

        TenantStat::for($tenant2)->set('active_users', 100);
        TenantStat::for($tenant2)->increase('active_users', 20);

        $tenant1Query = TenantStat::for($tenant1)->query('active_users');
        $tenant1Value = $tenant1Query->getValue(now());

        $tenant2Query = TenantStat::for($tenant2)->query('active_users');
        $tenant2Value = $tenant2Query->getValue(now());

        $this->assertEquals(60, $tenant1Value); // 50 + 10
        $this->assertEquals(120, $tenant2Value); // 100 + 20
    }

    /** @test */
    public function it_can_use_stat_name_first_syntax()
    {
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        TenantStat::for($tenant)->stat('api_calls')->increase();

        $this->assertDatabaseHas('tenant_stats', [
            'tenant_id' => $tenant->id,
            'name' => 'api_calls',
            'value' => 1,
        ]);
    }

    /** @test */
    public function it_can_track_stats_with_historical_timestamps()
    {
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        $timestamp = now()->subMonth();
        TenantStat::for($tenant)->increase('signups', 5, $timestamp);

        $this->assertDatabaseHas('tenant_stats', [
            'tenant_id' => $tenant->id,
            'name' => 'signups',
            'created_at' => $timestamp,
        ]);
    }

    /** @test */
    public function it_confirms_tenant_stat_is_not_tenant_aware()
    {
        $this->assertFalse(TenantStat::isTenantAware());
    }

    /** @test */
    public function tenant_stats_table_has_no_extra_tenant_id_column()
    {
        // The tenant_id column should be the foreign key, not a separate tenant scope column
        $columns = $this->app['db']->connection()->getSchemaBuilder()->getColumnListing('tenant_stats');

        $this->assertContains('tenant_id', $columns);

        // Count tenant_id columns - should only be one
        $tenantIdCount = count(array_filter($columns, fn($col) => $col === 'tenant_id'));
        $this->assertEquals(1, $tenantIdCount);
    }
}
