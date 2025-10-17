<?php

namespace Spatie\Stats\Tests;

use Carbon\Carbon;
use Spatie\Stats\Models\TimeStatsEvent;
use Spatie\Stats\Tests\Stats\ResponseTimeStats;
use Spatie\Stats\TimeDataPoint;
use Spatie\Stats\TimeStatsQuery;
use Spatie\Stats\TimeStatsWriter;

class TimeStatsQueryTest extends TimeStatsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2020-01-01 12:00:00');
    }

    /** @test */
    public function it_can_pass_and_receive_attributes()
    {
        $query = TimeStatsQuery::for(TimeStatsEvent::class, [
            'tenant_id' => 1,
            'employee_id' => 42,
        ]);

        $this->assertInstanceOf(TimeStatsQuery::class, $query);
        $this->assertSame([
            'tenant_id' => 1,
            'employee_id' => 42,
        ], $query->getAttributes());
    }

    /** @test */
    public function it_can_get_stats_for_base_time_stats_class()
    {
        ResponseTimeStats::record(60000, now()->subDays(13)); // 1 minute
        ResponseTimeStats::record(120000, now()->subDays(12)); // 2 minutes
        ResponseTimeStats::record(180000, now()->subDays(5)); // 3 minutes
        ResponseTimeStats::record(240000, now()->subDays(4)); // 4 minutes

        $stats = ResponseTimeStats::query()
            ->start(now()->subWeeks(2))
            ->end(now()->startOfWeek())
            ->groupByWeek()
            ->get();

        $this->assertCount(2, $stats);
        $this->assertInstanceOf(TimeDataPoint::class, $stats[0]);
    }

    /** @test */
    public function it_returns_time_data_points_with_correct_structure()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subDays(1)); // 1 minute
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subDays(1)); // 2 minutes
        TimeStatsWriter::for(TimeStatsEvent::class)->record(180000, now()->subDays(1)); // 3 minutes

        $stats = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subDays(2))
            ->end(now())
            ->groupByDay()
            ->get();

        // subDays(2) to now() creates 3 periods, data from subDays(1) is in period index 1
        $this->assertCount(3, $stats);
        $dataPoint = $stats[1];

        $this->assertEquals(3, $dataPoint->count);
        $this->assertEquals(360000, $dataPoint->totalDurationMs); // 6 minutes total
        $this->assertEquals(120000, $dataPoint->averageDurationMs); // 2 minutes average
        $this->assertEquals(60000, $dataPoint->minDurationMs); // 1 minute min
        $this->assertEquals(180000, $dataPoint->maxDurationMs); // 3 minutes max
        $this->assertEquals(120.0, $dataPoint->averageSeconds);
        $this->assertEquals(2.0, $dataPoint->averageMinutes);
    }

    /** @test */
    public function it_can_group_by_day()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subDays(2));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(180000, now()->subDays(1));

        $stats = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subDays(3))
            ->end(now())
            ->groupByDay()
            ->get();

        // subDays(3) to now() creates 4 periods
        $this->assertCount(4, $stats);

        // Day 1 - no data
        $this->assertEquals(0, $stats[0]->count);

        // Day 2 - 1 event (from subDays(2))
        $this->assertEquals(1, $stats[1]->count);
        $this->assertEquals(60000, $stats[1]->averageDurationMs);

        // Day 3 - 2 events (from subDays(1))
        $this->assertEquals(2, $stats[2]->count);
        $this->assertEquals(150000, $stats[2]->averageDurationMs); // (120000 + 180000) / 2
    }

    /** @test */
    public function it_can_group_by_hour()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subHours(2));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subHours(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(180000, now()->subHours(1));

        $stats = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subHours(3))
            ->end(now())
            ->groupByHour()
            ->get();

        // subHours(3) to now() creates 3 periods: 09:00-10:00, 10:00-11:00, 11:00-12:00
        $this->assertCount(3, $stats);
        $this->assertEquals(1, $stats[1]->count); // 10:00-11:00 hour (from subHours(2) = 10:00)
        $this->assertEquals(2, $stats[2]->count); // 11:00-12:00 hour (from subHours(1) = 11:00)
    }

    /** @test */
    public function it_can_group_by_week()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subWeeks(2)->addDay());
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subWeeks(1)->addDay());

        $stats = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subWeeks(3))
            ->end(now())
            ->groupByWeek()
            ->get();

        // subWeeks(3) to now() creates 4 periods
        $this->assertCount(4, $stats);
    }

    /** @test */
    public function it_can_group_by_month()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subMonths(2)->addDay());
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subMonths(1)->addDay());

        $stats = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subMonths(3))
            ->end(now())
            ->groupByMonth()
            ->get();

        // subMonths(3) to now() creates 4 periods
        $this->assertCount(4, $stats);
    }

    /** @test */
    public function it_returns_zeros_for_periods_with_no_data()
    {
        $stats = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subDay())
            ->end(now())
            ->groupByDay()
            ->get();

        $this->assertCount(2, $stats); // subDay() to now() creates 2 periods

        foreach ($stats as $stat) {
            $this->assertEquals(0, $stat->count);
            $this->assertEquals(0, $stat->totalDurationMs);
            $this->assertEquals(0, $stat->averageDurationMs);
            $this->assertEquals(0, $stat->minDurationMs);
            $this->assertEquals(0, $stat->maxDurationMs);
        }
    }

    /** @test */
    public function it_can_get_average_for_period()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(180000, now()->subDays(1));

        $average = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subDays(2))
            ->end(now())
            ->getAverage();

        // (60000 + 120000 + 180000) / 3 = 120000
        $this->assertEquals(120000, $average);
    }

    /** @test */
    public function it_can_get_count_for_period()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(180000, now()->subDays(1));

        $count = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subDays(2))
            ->end(now())
            ->getCount();

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_can_get_min_for_period()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(180000, now()->subDays(1));

        $min = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subDays(2))
            ->end(now())
            ->getMin();

        $this->assertEquals(60000, $min);
    }

    /** @test */
    public function it_can_get_max_for_period()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(180000, now()->subDays(1));

        $max = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subDays(2))
            ->end(now())
            ->getMax();

        $this->assertEquals(180000, $max);
    }

    /** @test */
    public function it_can_filter_by_attributes()
    {
        TimeStatsWriter::for(TimeStatsEvent::class, ['tenant_id' => 1, 'employee_id' => 1])
            ->record(60000, now()->subDays(1));

        TimeStatsWriter::for(TimeStatsEvent::class, ['tenant_id' => 1, 'employee_id' => 2])
            ->record(120000, now()->subDays(1));

        TimeStatsWriter::for(TimeStatsEvent::class, ['tenant_id' => 2, 'employee_id' => 1])
            ->record(180000, now()->subDays(1));

        // Query for tenant 1, employee 1
        $stats = TimeStatsQuery::for(TimeStatsEvent::class, [
            'tenant_id' => 1,
            'employee_id' => 1,
        ])
            ->start(now()->subDays(2))
            ->end(now())
            ->groupByDay()
            ->get();

        // subDays(2) to now() creates 3 periods, data from subDays(1) is in period index 1
        $this->assertCount(3, $stats);
        $this->assertEquals(1, $stats[1]->count);
        $this->assertEquals(60000, $stats[1]->averageDurationMs);

        // Query for tenant 1 (all employees)
        $stats = TimeStatsQuery::for(TimeStatsEvent::class, ['tenant_id' => 1])
            ->start(now()->subDays(2))
            ->end(now())
            ->groupByDay()
            ->get();

        $this->assertEquals(2, $stats[1]->count);
        $this->assertEquals(90000, $stats[1]->averageDurationMs); // (60000 + 120000) / 2
    }

    /** @test */
    public function it_only_queries_completed_events()
    {
        // Add some start events (should be ignored)
        TimeStatsWriter::for(TimeStatsEvent::class)->start('conv_123', now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->start('conv_456', now()->subDays(1));

        // Add completed events
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subDays(1));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subDays(1));

        $stats = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subDays(2))
            ->end(now())
            ->groupByDay()
            ->get();

        // subDays(2) to now() creates 3 periods, data from subDays(1) is in period index 1
        // Should only count completed events
        $this->assertCount(3, $stats);
        $this->assertEquals(2, $stats[1]->count);
    }

    /** @test */
    public function it_respects_time_range()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now()->subDays(10));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, now()->subDays(5));
        TimeStatsWriter::for(TimeStatsEvent::class)->record(180000, now()->subDays(1));

        $stats = TimeStatsQuery::for(TimeStatsEvent::class)
            ->start(now()->subDays(3))
            ->end(now())
            ->groupByDay()
            ->get();

        // Should only include the event from 1 day ago
        $totalCount = $stats->sum(fn($s) => $s->count);
        $this->assertEquals(1, $totalCount);
    }
}
