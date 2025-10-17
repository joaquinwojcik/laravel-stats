<?php

namespace Spatie\Stats\Tests;

use Carbon\Carbon;
use Spatie\Stats\Tests\Stats\ResponseTimeStats;
use Spatie\Stats\TimeStatsQuery;
use Spatie\Stats\TimeStatsWriter;

class BaseTimeStatsTest extends TimeStatsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2020-01-01 00:00:00');
    }

    /** @test */
    public function it_can_record_duration_directly()
    {
        ResponseTimeStats::record(60000); // 60 seconds in milliseconds

        $this->assertDatabaseHas('time_stats_events', [
            'name' => 'ResponseTimeStats',
            'duration_ms' => 60000,
            'type' => 'completed',
        ]);
    }

    /** @test */
    public function it_can_record_duration_at_a_given_timestamp()
    {
        $timestamp = now()->subHours(2);

        ResponseTimeStats::record(120000, $timestamp); // 2 minutes

        $this->assertDatabaseHas('time_stats_events', [
            'name' => 'ResponseTimeStats',
            'duration_ms' => 120000,
            'type' => 'completed',
            'started_at' => $timestamp,
        ]);
    }

    /** @test */
    public function it_can_record_duration_with_context()
    {
        ResponseTimeStats::record(60000, now(), [
            'tenant_id' => 1,
            'employee_id' => 42,
        ]);

        $this->assertDatabaseHas('time_stats_events', [
            'name' => 'ResponseTimeStats',
            'duration_ms' => 60000,
        ]);

        // Verify context separately since JSON comparison doesn't work in PostgreSQL
        $event = \Spatie\Stats\Models\TimeStatsEvent::where('name', 'ResponseTimeStats')
            ->where('duration_ms', 60000)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals([
            'tenant_id' => 1,
            'employee_id' => 42,
        ], $event->context);
    }

    /** @test */
    public function it_can_start_timing_an_event()
    {
        ResponseTimeStats::start('conversation_123');

        $this->assertDatabaseHas('time_stats_events', [
            'name' => 'ResponseTimeStats',
            'identifier' => 'conversation_123',
            'type' => 'start',
        ]);
    }

    /** @test */
    public function it_can_start_timing_at_a_given_timestamp()
    {
        $timestamp = now()->subMinutes(5);

        ResponseTimeStats::start('conversation_123', $timestamp);

        $this->assertDatabaseHas('time_stats_events', [
            'name' => 'ResponseTimeStats',
            'identifier' => 'conversation_123',
            'type' => 'start',
            'started_at' => $timestamp,
        ]);
    }

    /** @test */
    public function it_can_end_timing_and_calculate_duration()
    {
        $startTime = now()->subMinutes(5);
        $endTime = now();

        ResponseTimeStats::start('conversation_123', $startTime);
        $durationMs = ResponseTimeStats::end('conversation_123', $endTime);

        // 5 minutes = 300,000 milliseconds
        $this->assertEquals(300000, $durationMs);

        $this->assertDatabaseHas('time_stats_events', [
            'name' => 'ResponseTimeStats',
            'identifier' => 'conversation_123',
            'type' => 'completed',
            'duration_ms' => 300000,
        ]);
    }

    /** @test */
    public function it_returns_null_when_ending_without_start()
    {
        $durationMs = ResponseTimeStats::end('nonexistent_identifier');

        $this->assertNull($durationMs);
    }

    /** @test */
    public function it_returns_a_query_instance()
    {
        $query = ResponseTimeStats::query();

        $this->assertInstanceOf(TimeStatsQuery::class, $query);
    }

    /** @test */
    public function it_returns_a_writer_instance()
    {
        $writer = ResponseTimeStats::writer();

        $this->assertInstanceOf(TimeStatsWriter::class, $writer);
    }

    /** @test */
    public function it_returns_the_correct_name()
    {
        $stats = new ResponseTimeStats();

        $this->assertEquals('ResponseTimeStats', $stats->getName());
    }
}
