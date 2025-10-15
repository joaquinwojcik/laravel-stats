<?php

namespace Spatie\Stats;

use Illuminate\Database\Eloquent\Model;
use Spatie\Stats\Traits\HasStats;
use Spatie\Stats\Traits\IsTenantAware;

abstract class BaseStatModel extends Model
{
    use HasStats;

    protected $guarded = [];

    /**
     * Factory method to start fluent chain
     */
    public static function for(Model $model): StatBuilder
    {
        return new StatBuilder(static::class, $model);
    }

    /**
     * Each stat model defines its foreign key
     */
    abstract public static function getModelForeignKey(): string;

    /**
     * Check if this stat model is tenant-aware
     */
    public static function isTenantAware(): bool
    {
        return in_array(IsTenantAware::class, class_uses_recursive(static::class));
    }
}
