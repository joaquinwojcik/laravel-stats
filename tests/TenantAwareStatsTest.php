<?php

namespace Spatie\Stats\Tests;

use Carbon\Carbon;
use Spatie\Stats\Tests\TestClasses\Models\Tenant;
use Spatie\Stats\Tests\TestClasses\Models\User;
use Spatie\Stats\Tests\TestClasses\Models\UserStat;

class TenantAwareStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2020-01-01');

        // Create user_stats table
        $this->app['db']->connection()->getSchemaBuilder()->create('user_stats', function ($table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('type');
            $table->bigInteger('value');
            $table->timestamps();
        });

        // Create users table
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
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
    public function it_can_increase_stats_with_tenant_context()
    {
        $user = User::create(['name' => 'John']);
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        UserStat::for($user)->on($tenant)->increase('logins');

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'name' => 'logins',
            'value' => 1,
            'type' => 'change',
        ]);
    }

    /** @test */
    public function it_can_increase_stats_without_tenant_context()
    {
        $user = User::create(['name' => 'John']);

        UserStat::for($user)->increase('global_reputation');

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'tenant_id' => null,
            'name' => 'global_reputation',
            'value' => 1,
            'type' => 'change',
        ]);
    }

    /** @test */
    public function it_can_track_stats_for_same_user_across_different_tenants()
    {
        $user = User::create(['name' => 'John']);
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);

        UserStat::for($user)->on($tenant1)->increase('logins');
        UserStat::for($user)->on($tenant1)->increase('logins');
        UserStat::for($user)->on($tenant2)->increase('logins');

        // User has 2 logins in tenant1
        $this->assertEquals(2, UserStat::where('user_id', $user->id)
            ->where('tenant_id', $tenant1->id)
            ->where('name', 'logins')
            ->sum('value'));

        // User has 1 login in tenant2
        $this->assertEquals(1, UserStat::where('user_id', $user->id)
            ->where('tenant_id', $tenant2->id)
            ->where('name', 'logins')
            ->sum('value'));
    }

    /** @test */
    public function it_can_set_absolute_values_with_tenant()
    {
        $user = User::create(['name' => 'John']);
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        UserStat::for($user)->on($tenant)->set('posts_created', 42);

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'name' => 'posts_created',
            'value' => 42,
            'type' => 'set',
        ]);
    }

    /** @test */
    public function it_can_decrease_stats_with_tenant()
    {
        $user = User::create(['name' => 'John']);
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        UserStat::for($user)->on($tenant)->decrease('credits', 10);

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'name' => 'credits',
            'value' => -10,
            'type' => 'change',
        ]);
    }

    /** @test */
    public function it_can_use_stat_name_first_syntax()
    {
        $user = User::create(['name' => 'John']);
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        UserStat::for($user)->on($tenant)->stat('logins')->increase();

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'name' => 'logins',
            'value' => 1,
        ]);
    }

    /** @test */
    public function it_can_accept_tenant_id_as_integer()
    {
        $user = User::create(['name' => 'John']);
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        UserStat::for($user)->on($tenant->id)->increase('logins');

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'name' => 'logins',
        ]);
    }

    /** @test */
    public function it_can_accept_null_tenant_explicitly()
    {
        $user = User::create(['name' => 'John']);

        UserStat::for($user)->on(null)->increase('logins');

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'tenant_id' => null,
            'name' => 'logins',
        ]);
    }

    /** @test */
    public function it_can_track_stats_with_historical_timestamps()
    {
        $user = User::create(['name' => 'John']);
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        $timestamp = now()->subWeek();
        UserStat::for($user)->on($tenant)->increase('logins', 1, $timestamp);

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'name' => 'logins',
            'created_at' => $timestamp,
        ]);
    }

    /** @test */
    public function it_can_query_stats_with_tenant_context()
    {
        $user = User::create(['name' => 'John']);
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        UserStat::for($user)->on($tenant)->set('posts_created', 10, now()->subDays(5));
        UserStat::for($user)->on($tenant)->increase('posts_created', 5, now()->subDays(2));
        UserStat::for($user)->on($tenant)->increase('posts_created', 3, now());

        $stats = UserStat::for($user)
            ->on($tenant)
            ->query('posts_created')
            ->start(now()->subWeek())
            ->end(now())
            ->groupByDay()
            ->get();

        $this->assertNotEmpty($stats);
    }

    /** @test */
    public function it_isolates_queries_by_tenant()
    {
        $user = User::create(['name' => 'John']);
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);

        // Add stats to tenant1 with timestamps
        UserStat::for($user)->on($tenant1)->set('posts', 10, now()->subDays(2));
        UserStat::for($user)->on($tenant1)->increase('posts', 5, now()->subDay());

        // Add stats to tenant2 with timestamp
        UserStat::for($user)->on($tenant2)->set('posts', 20, now()->subDays(2));

        // Query tenant1 - should only see tenant1 stats
        $tenant1Value = UserStat::for($user)
            ->on($tenant1)
            ->query('posts')
            ->getValue(now());

        // Query tenant2 - should only see tenant2 stats
        $tenant2Value = UserStat::for($user)
            ->on($tenant2)
            ->query('posts')
            ->getValue(now());

        $this->assertEquals(15, $tenant1Value); // 10 + 5
        $this->assertEquals(20, $tenant2Value);

        // Verify isolation - stats are separate per tenant
        $tenant1Stats = UserStat::where('user_id', $user->id)
            ->where('tenant_id', $tenant1->id)
            ->where('name', 'posts')
            ->count();
        $tenant2Stats = UserStat::where('user_id', $user->id)
            ->where('tenant_id', $tenant2->id)
            ->where('name', 'posts')
            ->count();

        $this->assertEquals(2, $tenant1Stats); // set + increase
        $this->assertEquals(1, $tenant2Stats); // set only
    }

    /** @test */
    public function it_can_query_stats_without_tenant()
    {
        $user = User::create(['name' => 'John']);

        UserStat::for($user)->set('global_score', 100);
        UserStat::for($user)->increase('global_score', 50);

        $stats = UserStat::for($user)
            ->query('global_score')
            ->start(now()->subDay())
            ->end(now())
            ->groupByDay()
            ->get();

        $this->assertNotEmpty($stats);
    }

    /** @test */
    public function it_confirms_user_stat_is_tenant_aware()
    {
        $this->assertTrue(UserStat::isTenantAware());
    }

    /** @test */
    public function it_has_for_tenant_scope()
    {
        $user = User::create(['name' => 'John']);
        $tenant = Tenant::create(['name' => 'Acme Corp']);

        UserStat::for($user)->on($tenant)->increase('logins');

        $stats = UserStat::forTenant($tenant)->get();

        $this->assertCount(1, $stats);
        $this->assertEquals($tenant->id, $stats->first()->tenant_id);
    }
}
