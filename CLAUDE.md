# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel package for tracking statistical changes in database records over time. The package provides a lightweight solution to aggregate increments, decrements, and point-in-time snapshots of metrics, then query them grouped by time periods (minute, hour, day, week, month, year).

## Testing

Run the full test suite:
```bash
composer test
```

Run tests with coverage report:
```bash
composer test-coverage
```

Run a single test file:
```bash
vendor/bin/phpunit tests/StatsQueryTest.php
```

Run a specific test method:
```bash
vendor/bin/phpunit --filter testMethodName
```

Note: Tests require a MySQL database. The default test configuration uses:
- Database: `laravel_stats_test`
- Username: `root`
- Host: `127.0.0.1`
- Port: `3306`

These settings are defined in `phpunit.xml.dist` and can be overridden with environment variables.

## Architecture

### Core Components

**BaseStats** (`src/BaseStats.php`)
- Abstract base class for creating custom stats classes
- Provides static convenience methods: `increase()`, `decrease()`, `set()`, `query()`, `writer()`
- Uses class name as the stat identifier by default (override `getName()` to customize)
- All custom stats classes extend this (e.g., `SubscriptionStats extends BaseStats`)

**StatsWriter** (`src/StatsWriter.php`)
- Handles writing stat events to the database
- Supports three operations:
  - `increase()`: Records a positive change (TYPE_CHANGE)
  - `decrease()`: Records a negative change (TYPE_CHANGE as negative value)
  - `set()`: Records an absolute value snapshot (TYPE_SET)
- Works with Models, Relations, or class strings via `for()` static constructor
- Accepts optional timestamp to record historical events

**StatsQuery** (`src/StatsQuery.php`)
- Handles reading and aggregating stats from the database
- Supports time-based grouping: `groupByMinute/Hour/Day/Week/Month/Year()`
- Calculates values by combining SET events with CHANGE deltas
- Multi-database support with custom date formatting for MySQL, PostgreSQL, and SQLite
- Returns collections of `DataPoint` objects with start/end times, value, increments, decrements, and difference

**DataPoint** (`src/DataPoint.php`)
- Value object representing aggregated stats for a time period
- Contains: `start`, `end`, `value`, `increments`, `decrements`, `difference`
- Two event types: `TYPE_SET` (absolute value) and `TYPE_CHANGE` (delta)

**StatsEvent Model** (`src/Models/StatsEvent.php`)
- Default Eloquent model for storing stats events
- Uses `HasStats` trait for period grouping scopes
- Stores: `name`, `type`, `value`, `created_at`

**HasStats Trait** (`src/Traits/HasStats.php`)
- Provides query scopes for stats models:
  - `groupByPeriod()`: Groups results by time period with DB-specific formatting
  - `increments()`: Filters to positive values only
  - `decrements()`: Filters to negative values only
- Applied to custom models to enable stats functionality

### Data Flow

1. **Writing Stats**: User code → `BaseStats::increase/decrease/set()` → `StatsWriter` → Database (stats_events table)

2. **Reading Stats**: User code → `BaseStats::query()` → `StatsQuery` → Aggregates TYPE_SET and TYPE_CHANGE events → Returns `DataPoint[]`

3. **Value Calculation**: StatsQuery finds the nearest TYPE_SET event before the period, then applies all TYPE_CHANGE deltas since that snapshot to calculate current value

### Tenant-Aware Stats (New Architecture)

**BaseStatModel** (`src/BaseStatModel.php`)
- Abstract base class for all stat models (replacing individual `BaseStats` subclasses)
- Factory method `for(Model)` returns `StatBuilder` for fluent API
- `isTenantAware()` method checks if model uses `IsTenantAware` trait
- Each stat model must implement `getModelForeignKey()` to define its foreign key

**IsTenantAware Trait** (`src/Traits/IsTenantAware.php`)
- Opt-in trait that marks a stat model as tenant-aware
- Adds `tenant_id` column to the model's table schema
- Provides `tenant()` relationship and `forTenant()` scope
- Models WITHOUT this trait are non-tenant-aware (e.g., TenantStat)

**StatBuilder** (`src/StatBuilder.php`)
- Fluent API for writing and querying stats
- Methods: `for(Model)`, `on(Tenant)`, `stat(string)`, `increase()`, `decrease()`, `set()`, `query()`
- Validates that `on()` is only called on tenant-aware models (throws `BadMethodCallException` otherwise)
- Automatically includes/excludes `tenant_id` based on model's tenant awareness

**Artisan Command** (`src/Console/MakeStatsCommand.php`)
- `php artisan stats:make User --tenant-aware` creates tenant-aware stat model
- `php artisan stats:make Tenant` creates non-tenant-aware stat model
- Generates migration (with/without `tenant_id` column) and model class
- Uses stub templates from `src/Stubs/`

### Stat Model Patterns

**Tenant-Aware Stats** (e.g., UserStat, PostStat):
- Track entity stats WITHIN tenant contexts
- Table has `tenant_id` (nullable), `{model}_id`, `name`, `type`, `value`
- Usage: `UserStat::for($user)->on($tenant)->increase('logins')`
- Same user can have different stats in different tenants

**Non-Tenant-Aware Stats** (e.g., TenantStat, SystemStat):
- Track entity's OWN metrics (no tenant scope)
- Table has only `{model}_id`, `name`, `type`, `value` (no tenant_id column)
- Usage: `TenantStat::for($tenant)->increase('active_users')`
- Calling `->on()` throws exception

### Data Flow (Tenant-Aware)

1. **Writing Stats**: `UserStat::for($user)->on($tenant)->increase('logins')` → `StatBuilder` → Creates record with `user_id`, `tenant_id`, `name`, `type`, `value`

2. **Querying Stats**: `UserStat::for($user)->on($tenant)->query('logins')` → `StatBuilder` filters by `user_id`, `tenant_id`, `name` → `StatsQuery` → Returns `DataPoint[]`

3. **Tenant Isolation**: Same user can have independent stats per tenant, or global stats (tenant_id = NULL)

### Extensibility

Custom models can use stats by:
1. Creating a table with `type`, `value`, `created_at` columns
2. Adding the `HasStats` trait to the model
3. Using `StatsWriter::for(CustomModel::class)` and `StatsQuery::for(CustomModel::class)`

Relations are supported: `StatsWriter::for($tenant->orderStats())` allows per-relationship stat tracking.

**Modern Approach (Recommended):**
1. Run `php artisan stats:make YourModel --tenant-aware` to scaffold
2. Extend generated model if needed
3. Use fluent API: `YourModelStat::for($model)->on($tenant)->increase('metric')`

## Database Schema

The package publishes a migration that creates a `stats_events` table:
```bash
php artisan vendor:publish --provider="Spatie\Stats\StatsServiceProvider" --tag="stats-migrations"
php artisan migrate
```

The migration stub is located at `database/migrations/create_stats_tables.php.stub`.