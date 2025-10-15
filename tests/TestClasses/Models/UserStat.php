<?php

namespace Spatie\Stats\Tests\TestClasses\Models;

use Spatie\Stats\BaseStatModel;
use Spatie\Stats\Tests\TestClasses\Models\User;
use Spatie\Stats\Traits\IsTenantAware;

class UserStat extends BaseStatModel
{
    use IsTenantAware;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getModelForeignKey(): string
    {
        return 'user_id';
    }
}
