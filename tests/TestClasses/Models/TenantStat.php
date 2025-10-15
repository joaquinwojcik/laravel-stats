<?php

namespace Spatie\Stats\Tests\TestClasses\Models;

use Spatie\Stats\BaseStatModel;

class TenantStat extends BaseStatModel
{
    protected $guarded = [];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public static function getModelForeignKey(): string
    {
        return 'tenant_id';
    }
}
