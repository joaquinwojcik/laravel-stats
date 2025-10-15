<?php

namespace Spatie\Stats;

use BadMethodCallException;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class StatBuilder
{
    protected string $statModelClass;
    protected Model $model;
    protected ?int $tenantId = null;
    protected ?string $statName = null;

    public function __construct(string $statModelClass, Model $model)
    {
        $this->statModelClass = $statModelClass;
        $this->model = $model;
    }

    /**
     * Set tenant context - only available for tenant-aware stats
     */
    public function on(Model|int|null $tenant): self
    {
        if (! $this->statModelClass::isTenantAware()) {
            throw new BadMethodCallException(
                "on() can only be called on tenant-aware stat models. " .
                "{$this->statModelClass} does not use the IsTenantAware trait."
            );
        }

        $this->tenantId = $tenant instanceof Model ? $tenant->id : $tenant;

        return $this;
    }

    /**
     * Set stat name for subsequent operations
     */
    public function stat(string $name): self
    {
        $this->statName = $name;

        return $this;
    }

    /**
     * Increase stat value
     */
    public function increase(string $name = null, int $amount = 1, ?DateTimeInterface $timestamp = null): Model
    {
        $name = $name ?? $this->statName;

        if ($name === null) {
            throw new InvalidArgumentException('Stat name is required');
        }

        return $this->createStatEvent($name, DataPoint::TYPE_CHANGE, $amount, $timestamp);
    }

    /**
     * Decrease stat value
     */
    public function decrease(string $name = null, int $amount = 1, ?DateTimeInterface $timestamp = null): Model
    {
        $name = $name ?? $this->statName;

        if ($name === null) {
            throw new InvalidArgumentException('Stat name is required');
        }

        return $this->createStatEvent($name, DataPoint::TYPE_CHANGE, -$amount, $timestamp);
    }

    /**
     * Set stat to absolute value
     */
    public function set(string $name = null, int $value, ?DateTimeInterface $timestamp = null): Model
    {
        $name = $name ?? $this->statName;

        if ($name === null) {
            throw new InvalidArgumentException('Stat name is required');
        }

        return $this->createStatEvent($name, DataPoint::TYPE_SET, $value, $timestamp);
    }

    /**
     * Query stats - returns StatsQuery for fluent querying
     */
    public function query(string $name = null): StatsQuery
    {
        $name = $name ?? $this->statName;

        if ($name === null) {
            throw new InvalidArgumentException('Stat name is required');
        }

        $modelClass = $this->statModelClass;
        $foreignKey = $modelClass::getModelForeignKey();

        $query = $modelClass::where($foreignKey, $this->model->id)
            ->where('name', $name);

        // Only filter by tenant_id if model is tenant-aware
        if ($modelClass::isTenantAware()) {
            if ($this->tenantId !== null) {
                $query->where('tenant_id', $this->tenantId);
            } else {
                $query->whereNull('tenant_id');
            }
        }

        return StatsQuery::for($query);
    }

    /**
     * Create a stat event record
     */
    protected function createStatEvent(string $name, string $type, int $value, ?DateTimeInterface $timestamp): Model
    {
        $modelClass = $this->statModelClass;
        $foreignKey = $modelClass::getModelForeignKey();

        $attributes = [
            $foreignKey => $this->model->id,
            'name' => $name,
            'type' => $type,
            'value' => $value,
            'created_at' => $timestamp ?? now(),
        ];

        // Only add tenant_id if model is tenant-aware
        if ($modelClass::isTenantAware()) {
            $attributes['tenant_id'] = $this->tenantId;
        }

        return $modelClass::create($attributes);
    }
}
