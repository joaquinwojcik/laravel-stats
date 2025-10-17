<?php

namespace Spatie\Stats;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TimeStatsQuery
{
    private Model|Relation|string $subject;
    private array $attributes = [];
    protected string $period;
    protected DateTimeInterface $start;
    protected DateTimeInterface $end;

    public function __construct(Model|Relation|string $subject, array $attributes = [])
    {
        $this->subject = $subject;
        $this->attributes = $attributes;
        $this->period = 'day';
        $this->start = now()->subWeek();
        $this->end = now();
    }

    public static function for(Model|Relation|string $subject, array $attributes = []): self
    {
        return new self($subject, $attributes);
    }

    public function groupByYear(): self
    {
        $this->period = 'year';

        return $this;
    }

    public function groupByMonth(): self
    {
        $this->period = 'month';

        return $this;
    }

    public function groupByWeek(): self
    {
        $this->period = 'week';

        return $this;
    }

    public function groupByDay(): self
    {
        $this->period = 'day';

        return $this;
    }

    public function groupByHour(): self
    {
        $this->period = 'hour';

        return $this;
    }

    public function groupByMinute(): self
    {
        $this->period = 'minute';

        return $this;
    }

    public function start(DateTimeInterface $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function end(DateTimeInterface $end): self
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get time statistics grouped by the specified period
     *
     * @return Collection|TimeDataPoint[]
     */
    public function get(): Collection
    {
        $periods = $this->generatePeriods();
        $stats = $this->getStatsPerPeriod();

        return $periods->map(function (array $periodBoundaries) use ($stats) {
            [$periodStart, $periodEnd, $periodKey] = $periodBoundaries;

            $periodStats = $stats->get($periodKey);

            if (! $periodStats) {
                return new TimeDataPoint(
                    start: $periodStart,
                    end: $periodEnd,
                    count: 0,
                    totalDurationMs: 0,
                    averageDurationMs: 0,
                    minDurationMs: 0,
                    maxDurationMs: 0,
                    averageSeconds: 0.0,
                    averageMinutes: 0.0,
                );
            }

            $avgMs = (int) $periodStats['avg_duration_ms'];

            return new TimeDataPoint(
                start: $periodStart,
                end: $periodEnd,
                count: (int) $periodStats['count'],
                totalDurationMs: (int) $periodStats['total_duration_ms'],
                averageDurationMs: $avgMs,
                minDurationMs: (int) $periodStats['min_duration_ms'],
                maxDurationMs: (int) $periodStats['max_duration_ms'],
                averageSeconds: round($avgMs / 1000, 2),
                averageMinutes: round($avgMs / 60000, 2),
            );
        });
    }

    /**
     * Get the average duration for the entire period
     *
     * @return int Average duration in milliseconds
     */
    public function getAverage(): int
    {
        $result = $this->queryStats()
            ->where('type', TimeDataPoint::TYPE_COMPLETED)
            ->where('started_at', '>=', $this->start)
            ->where('started_at', '<', $this->end)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms');

        return (int) ($result ?? 0);
    }

    /**
     * Get total count of completed events
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->queryStats()
            ->where('type', TimeDataPoint::TYPE_COMPLETED)
            ->where('started_at', '>=', $this->start)
            ->where('started_at', '<', $this->end)
            ->whereNotNull('duration_ms')
            ->count();
    }

    /**
     * Get the minimum duration
     *
     * @return int Duration in milliseconds
     */
    public function getMin(): int
    {
        $result = $this->queryStats()
            ->where('type', TimeDataPoint::TYPE_COMPLETED)
            ->where('started_at', '>=', $this->start)
            ->where('started_at', '<', $this->end)
            ->whereNotNull('duration_ms')
            ->min('duration_ms');

        return (int) ($result ?? 0);
    }

    /**
     * Get the maximum duration
     *
     * @return int Duration in milliseconds
     */
    public function getMax(): int
    {
        $result = $this->queryStats()
            ->where('type', TimeDataPoint::TYPE_COMPLETED)
            ->where('started_at', '>=', $this->start)
            ->where('started_at', '<', $this->end)
            ->whereNotNull('duration_ms')
            ->max('duration_ms');

        return (int) ($result ?? 0);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    protected function generatePeriods(): Collection
    {
        $data = collect();
        $currentDateTime = (new Carbon($this->start))->startOf($this->period);

        do {
            $data->push([
                $currentDateTime->copy(),
                $currentDateTime->copy()->add(1, $this->period),
                $currentDateTime->format($this->getPeriodTimestampFormat()),
            ]);

            $currentDateTime->add(1, $this->period);
        } while ($currentDateTime->lt($this->end));

        return $data;
    }

    protected function getPeriodTimestampFormat(): string
    {
        return match ($this->period) {
            'year' => 'Y',
            'month' => 'Y-m',
            'week' => 'oW',
            'day' => 'Y-m-d',
            'hour' => 'Y-m-d H',
            'minute' => 'Y-m-d H:i',
        };
    }

    protected function getStatsPerPeriod(): Collection
    {
        $periodDateFormat = $this->getPeriodDateFormat();

        return $this->queryStats()
            ->where('type', TimeDataPoint::TYPE_COMPLETED)
            ->where('started_at', '>=', $this->start)
            ->where('started_at', '<', $this->end)
            ->whereNotNull('duration_ms')
            ->selectRaw('count(*) as count')
            ->selectRaw('sum(duration_ms) as total_duration_ms')
            ->selectRaw('avg(duration_ms) as avg_duration_ms')
            ->selectRaw('min(duration_ms) as min_duration_ms')
            ->selectRaw('max(duration_ms) as max_duration_ms')
            ->selectRaw("{$periodDateFormat} as period")
            ->groupByRaw($periodDateFormat)
            ->get()
            ->keyBy('period');
    }

    protected function getPeriodDateFormat(): string
    {
        $dbDriver = config('database.connections.'.config('database.default', 'mysql').'.driver', 'mysql');

        if ($dbDriver === 'pgsql') {
            return match ($this->period) {
                'year' => "to_char(started_at, 'YYYY')",
                'month' => "to_char(started_at, 'YYYY-MM')",
                'week' => "to_char(started_at, 'IYYYIW')",
                'day' => "to_char(started_at, 'YYYY-MM-DD')",
                'hour' => "to_char(started_at, 'YYYY-MM-DD HH24')",
                'minute' => "to_char(started_at, 'YYYY-MM-DD HH24:MI')",
            };
        }

        if ($dbDriver === 'sqlite') {
            return match ($this->period) {
                'year' => "strftime('%Y', started_at)",
                'month' => "strftime('%Y-%m', started_at)",
                'week' => "strftime('%Y%W', started_at)",
                'day' => "strftime('%Y-%m-%d', started_at)",
                'hour' => "strftime('%Y-%m-%d %H', started_at)",
                'minute' => "strftime('%Y-%m-%d %H:%M', started_at)",
            };
        }

        return match ($this->period) {
            'year' => "date_format(started_at, '%Y')",
            'month' => "date_format(started_at, '%Y-%m')",
            'week' => "yearweek(started_at, 3)",
            'day' => "date_format(started_at, '%Y-%m-%d')",
            'hour' => "date_format(started_at, '%Y-%m-%d %H')",
            'minute' => "date_format(started_at, '%Y-%m-%d %H:%i')",
        };
    }

    protected function queryStats(): Builder
    {
        if ($this->subject instanceof Relation) {
            return $this->subject->getQuery()->clone()->where($this->attributes);
        }

        $subject = $this->subject;
        if (is_string($subject) && class_exists($subject)) {
            $subject = new $subject;
        }

        return $subject->newQuery()->where($this->attributes);
    }
}
