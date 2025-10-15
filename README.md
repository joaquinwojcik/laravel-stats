# Track application stat changes over time

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-stats.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-stats)
[![Tests](https://github.com/spatie/laravel-stats/actions/workflows/run-tests.yml/badge.svg)](https://github.com/spatie/laravel-stats/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-stats.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-stats)

This package is a lightweight solution to summarize changes in your database over time. Here's a quick example where we are going to track the number of subscriptions and cancellations over time.

First, you should create a stats class.

```php
use Spatie\Stats\BaseStats;

class SubscriptionStats extends BaseStats {}
```

Next, you can call `increase` on it when somebody subscribes, and `decrease` when somebody cancels their plan.

```php
SubscriptionStats::increase(); // execute whenever somebody subscribes
SubscriptionStats::decrease() // execute whenever somebody cancels the subscription;
```

With this in place, you can query the stats. Here's how you can get the subscription stats for the past two months,
grouped by week.

```php
use Spatie\Stats\StatsQuery;

$stats = SubscriptionStats::query()
    ->start(now()->subMonths(2))
    ->end(now()->subSecond())
    ->groupByWeek()
    ->get();
```


This will return an array like this one:

```php 
[
    [
        'start' => '2020-01-01',
        'end' => '2020-01-08',
        'value' => 102,
        'increments' => 32,
        'decrements' => 20,
        'difference' => 12,
    ],
    [
        'start' => '2020-01-08',
        'end' => '2020-01-15',
        'value' => 114,
        'increments' => 63,
        'decrements' => 30,
        'difference' => 33,
    ],
]
```

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-stats.jpg?t=2" width="419px" />](https://spatie.be/github-ad-click/laravel-stats)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can
support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.
You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards
on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/laravel-stats
```

You must publish and run the migrations with:

```bash
php artisan vendor:publish --provider="Spatie\Stats\StatsServiceProvider" --tag="stats-migrations"
php artisan migrate
```

## Usage

### Step 1: create a stats class

First, you should create a stats class. This class is responsible for configuration how a particular statistic is
stored. By default, it needs no configuration at all.

```php
use Spatie\Stats\BaseStats;

class SubscriptionStats extends BaseStats {}
```

By default, the name of the class will be used to store the statistics in the database. To customize the used key, use `getName`

```php
use Spatie\Stats\BaseStats;

class SubscriptionStats extends BaseStats
{
    public function getName() : string{
        return 'my-custom-name'; // stats will be stored with using name `my-custom-name`
    }
}
```

## Step 2: call increase and decrease or set a fixed value

Next, you can call `increase`, `decrease` when the stat should change.  In this particular case, you should call `increase` on it when somebody subscribes, and `decrease` when somebody cancels their plan.

```php
SubscriptionStats::increase(); // execute whenever somebody subscribes
SubscriptionStats::decrease(); // execute whenever somebody cancels the subscription;
```

Instead of manually increasing and decreasing the stat, you can directly set it. This is useful when your particular stat does not get calculated by your own app, but lives elsewhere.  Using the subscription example, let's image that subscriptions live elsewhere, and that there's an API call to get the count.

```php
$count = AnAPi::getSubscriptionCount(); 

SubscriptionStats::set($count);
```

By default, that `increase`, `decrease` and `set` methods assume that the event that caused your stats to change, happened right now. Optionally, you can pass a date time as a second parameter to these methods. Your stat change will be recorded as if it happened on that moment.

```php
SubscriptionStats::increase(1, $subscription->created_at); 
```

### Step 3: query the stats

With this in place, you can query the stats. You can fetch stats for a certain period and group them by minute, hour, day, week, month, or year. 

Here's how you can get the subscription stats for the past two months,
grouped by week.

```php
$stats = SubscriptionStats::query()
    ->start(now()->subMonths(2))
    ->end(now()->subSecond())
    ->groupByWeek()
    ->get();
```

This will return an array containing arrayable `Spatie\Stats\DataPoint` objects. These objects can be cast to arrays like this:

```php 
// output of $stats->toArray():
[
    [
        'start' => '2020-01-01',
        'end' => '2020-01-08',
        'value' => 102,
        'increments' => 32,
        'decrements' => 20,
        'difference' => 12,
    ],
    [
        'start' => '2020-01-08',
        'end' => '2020-01-15',
        'value' => 114,
        'increments' => 63,
        'decrements' => 30,
        'difference' => 33,
    ],
]
```

## Tenant-Aware Stats (New)

This package now supports flexible tenant-aware statistics with a powerful fluent API. You can track stats per model with optional tenant context.

### Creating Stat Models

Use the artisan command to generate stat models:

```bash
# Create a tenant-aware stat model (includes tenant_id column)
php artisan stats:make User --tenant-aware

# Create a non-tenant-aware stat model (no tenant_id column)
php artisan stats:make Tenant
```

This generates:
- A migration file with the appropriate schema
- A model class extending `BaseStatModel`

### Tenant-Aware Stats

For models like User or Post where you want to track stats within tenant contexts:

```php
use App\Models\UserStat;

// Track user stats in specific tenant
UserStat::for($user)->on($tenant)->increase('logins');
UserStat::for($user)->on($tenant)->set('posts_created', 42);
UserStat::for($user)->on($tenant)->decrease('credits', 10);

// Same user, different tenants - stats are isolated
UserStat::for($user)->on($tenant1)->increase('logins');
UserStat::for($user)->on($tenant2)->increase('logins');

// Track global stats without tenant context
UserStat::for($user)->increase('global_reputation');

// Query stats for specific tenant
$stats = UserStat::for($user)
    ->on($tenant)
    ->query('logins')
    ->start(now()->subMonth())
    ->groupByDay()
    ->get();
```

### Non-Tenant-Aware Stats

For models like Tenant itself, where you want to track the model's own metrics:

```php
use App\Models\TenantStat;

// Track tenant's own metrics (no tenant context needed)
TenantStat::for($tenant)->increase('active_users');
TenantStat::for($tenant)->set('storage_mb', 1024);
TenantStat::for($tenant)->increase('api_calls');

// Query tenant's metrics
$stats = TenantStat::for($tenant)
    ->query('active_users')
    ->start(now()->subMonth())
    ->groupByDay()
    ->get();
```

**Note:** Calling `->on()` on a non-tenant-aware stat model will throw a `BadMethodCallException`.

### Historical Tracking

Track stats with custom timestamps:

```php
UserStat::for($user)->on($tenant)->increase('purchases', 1, $order->created_at);
TenantStat::for($tenant)->set('total_revenue', 50000, now()->subMonth());
```

### Alternative Syntax

Set stat name first, then perform operations:

```php
UserStat::for($user)->on($tenant)->stat('logins')->increase();
TenantStat::for($tenant)->stat('api_calls')->increase(100);
```

### How It Works

- **Tenant-aware models** (using `IsTenantAware` trait) have a `tenant_id` column and support `->on($tenant)`
- **Non-tenant-aware models** track the model's own stats without an additional tenant scope
- Each stat model explicitly declares if it's tenant-aware via the trait
- Stats are stored in separate tables per model (`user_stats`, `tenant_stats`, etc.)

## Extended Use-Cases

### Read and Write from a custom Model

* Create a new table with `type (string)`, `value (bigInt)`, `created_at`, `updated_at` fields
* Create a model and add `HasStats`-trait 

```php
StatsWriter::for(MyCustomModel::class)->set(123)
StatsWriter::for(MyCustomModel::class, ['custom_column' => '123'])->increase(1)
StatsWriter::for(MyCustomModel::class, ['another_column' => '234'])->decrease(1, now()->subDay())

$stats = StatsQuery::for(MyCustomModel::class)
    ->start(now()->subMonths(2))
    ->end(now()->subSecond())
    ->groupByWeek()
    ->get();
    
// OR

$stats = StatsQuery::for(MyCustomModel::class, ['additional_column' => '123'])
    ->start(now()->subMonths(2))
    ->end(now()->subSecond())
    ->groupByWeek()
    ->get(); 
```

### Read and Write from a HasMany-Relationship 

```php
$tenant = Tenant::find(1) 

StatsWriter::for($tenant->orderStats(), ['payment_type_column' => 'recurring'])->increment(1)

$stats = StatsQuery::for($tenant->orderStats(), , ['payment_type_column' => 'recurring'])
    ->start(now()->subMonths(2))
    ->end(now()->subSecond())
    ->groupByWeek()
    ->get();
```

## Testing

``` bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Alex Vanderbist](https://github.com/AlexVanderbist)
- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
