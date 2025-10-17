<?php

namespace Spatie\Stats;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;

class TimeDataPoint implements Arrayable
{
    const TYPE_START = 'start';
    const TYPE_COMPLETED = 'completed';

    public function __construct(
        public Carbon $start,
        public Carbon $end,
        public int $count,              // Number of events in this period
        public int $totalDurationMs,    // Sum of all durations in ms
        public int $averageDurationMs,  // Average duration in ms
        public int $minDurationMs,      // Minimum duration in ms
        public int $maxDurationMs,      // Maximum duration in ms
        public float $averageSeconds,   // Average duration in seconds (convenience)
        public float $averageMinutes,   // Average duration in minutes (convenience)
    ) {
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
            'count' => $this->count,
            'total_duration_ms' => $this->totalDurationMs,
            'average_duration_ms' => $this->averageDurationMs,
            'min_duration_ms' => $this->minDurationMs,
            'max_duration_ms' => $this->maxDurationMs,
            'average_seconds' => $this->averageSeconds,
            'average_minutes' => $this->averageMinutes,
        ];
    }

    /**
     * Get average duration in seconds
     */
    public function getAverageDurationInSeconds(): float
    {
        return round($this->averageDurationMs / 1000, 2);
    }

    /**
     * Get average duration in minutes
     */
    public function getAverageDurationInMinutes(): float
    {
        return round($this->averageDurationMs / 60000, 2);
    }

    /**
     * Get total duration in seconds
     */
    public function getTotalDurationInSeconds(): float
    {
        return round($this->totalDurationMs / 1000, 2);
    }

    /**
     * Get total duration in minutes
     */
    public function getTotalDurationInMinutes(): float
    {
        return round($this->totalDurationMs / 60000, 2);
    }
}
