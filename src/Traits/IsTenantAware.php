<?php

namespace Spatie\Stats\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait IsTenantAware
{
    public function tenant()
    {
        // Assumes a Tenant model exists - can be customized
        $tenantModel = config('stats.tenant_model', 'App\\Models\\Tenant');

        return $this->belongsTo($tenantModel, 'tenant_id');
    }

    public function scopeForTenant(Builder $query, Model|int|null $tenant)
    {
        if ($tenant === null) {
            return $query->whereNull('tenant_id');
        }

        $tenantId = $tenant instanceof Model ? $tenant->id : $tenant;

        return $query->where('tenant_id', $tenantId);
    }
}
