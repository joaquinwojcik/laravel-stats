<?php

namespace Spatie\Stats\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Stats\TimeDataPoint;

class TimeStatsEvent extends Model
{
    /**
     * @deprecated use TimeDataPoint::TYPE_START
     */
    const TYPE_START = TimeDataPoint::TYPE_START;

    /**
     * @deprecated use TimeDataPoint::TYPE_COMPLETED
     */
    const TYPE_COMPLETED = TimeDataPoint::TYPE_COMPLETED;

    protected $casts = [
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'context' => 'array',
    ];

    protected $guarded = [];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('stats.time_stats_table_name', 'time_stats_events');
    }
}
