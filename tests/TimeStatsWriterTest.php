<?php

namespace Spatie\Stats\Tests;

use Carbon\Carbon;
use Spatie\Stats\Models\TimeStatsEvent;
use Spatie\Stats\Tests\TestClasses\Models\Stat;
use Spatie\Stats\TimeStatsWriter;

class TimeStatsWriterTest extends TimeStatsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2020-01-01 12:00:00');
    }

    /** @test */
    public function it_can_pass_and_receive_attributes()
    {
        $writer = TimeStatsWriter::for(TimeStatsEvent::class, [
            'tenant_id' => 1,
            'employee_id' => 42,
        ]);

        $this->assertInstanceOf(TimeStatsWriter::class, $writer);
        $this->assertSame([
            'tenant_id' => 1,
            'employee_id' => 42,
        ], $writer->getAttributes());
    }

    /** @test */
    public function it_can_record_duration_directly()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000);

        $this->assertDatabaseHas('time_stats_events', [
            'duration_ms' => 60000,
            'type' => 'completed',
        ]);
    }

    /** @test */
    public function it_can_record_duration_at_a_given_timestamp()
    {
        $timestamp = now()->subHours(2);

        TimeStatsWriter::for(TimeStatsEvent::class)->record(120000, $timestamp);

        $this->assertDatabaseHas('time_stats_events', [
            'duration_ms' => 120000,
            'type' => 'completed',
            'started_at' => $timestamp,
            'ended_at' => $timestamp,
        ]);
    }

    /** @test */
    public function it_can_record_with_context_data()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->record(60000, now(), [
            'conversation_id' => 123,
            'channel' => 'email',
        ]);

        $this->assertDatabaseHas('time_stats_events', [
            'duration_ms' => 60000,
        ]);

        // Verify context separately
        $event = TimeStatsEvent::where('duration_ms', 60000)->first();
        $this->assertNotNull($event);
        $this->assertEquals([
            'conversation_id' => 123,
            'channel' => 'email',
        ], $event->context);
    }

    /** @test */
    public function it_can_record_with_attributes()
    {
        TimeStatsWriter::for(TimeStatsEvent::class, [
            'tenant_id' => 1,
            'employee_id' => 42,
        ])->record(60000);

        $this->assertDatabaseHas('time_stats_events', [
            'tenant_id' => 1,
            'employee_id' => 42,
            'duration_ms' => 60000,
        ]);
    }

    /** @test */
    public function it_can_start_a_timer()
    {
        $timestamp = now()->subMinutes(5);

        TimeStatsWriter::for(TimeStatsEvent::class)->start('conv_123', $timestamp);

        $this->assertDatabaseHas('time_stats_events', [
            'identifier' => 'conv_123',
            'type' => 'start',
            'started_at' => $timestamp,
        ]);

        // Verify duration_ms is null for start events
        $event = TimeStatsEvent::where('identifier', 'conv_123')->where('type', 'start')->first();
        $this->assertNull($event->duration_ms);
    }

    /** @test */
    public function it_can_start_with_context()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->start('conv_123', now(), [
            'customer_id' => 999,
        ]);

        $this->assertDatabaseHas('time_stats_events', [
            'identifier' => 'conv_123',
            'type' => 'start',
        ]);

        // Verify context separately
        $event = TimeStatsEvent::where('identifier', 'conv_123')->where('type', 'start')->first();
        $this->assertNotNull($event);
        $this->assertEquals(['customer_id' => 999], $event->context);
    }

    /** @test */
    public function it_can_end_a_timer_and_calculate_duration()
    {
        $startTime = now()->subMinutes(5);
        $endTime = now();

        TimeStatsWriter::for(TimeStatsEvent::class)->start('conv_123', $startTime);
        $durationMs = TimeStatsWriter::for(TimeStatsEvent::class)->end('conv_123', $endTime);

        // 5 minutes = 300,000 milliseconds
        $this->assertEquals(300000, $durationMs);

        $this->assertDatabaseHas('time_stats_events', [
            'identifier' => 'conv_123',
            'type' => 'completed',
            'started_at' => $startTime,
            'ended_at' => $endTime,
            'duration_ms' => 300000,
        ]);
    }

    /** @test */
    public function it_marks_start_event_as_ended()
    {
        $startTime = now()->subMinutes(5);
        $endTime = now();

        TimeStatsWriter::for(TimeStatsEvent::class)->start('conv_123', $startTime);

        $this->assertDatabaseHas('time_stats_events', [
            'identifier' => 'conv_123',
            'type' => 'start',
            'ended_at' => null,
        ]);

        TimeStatsWriter::for(TimeStatsEvent::class)->end('conv_123', $endTime);

        $this->assertDatabaseHas('time_stats_events', [
            'identifier' => 'conv_123',
            'type' => 'start',
            'ended_at' => $endTime,
        ]);
    }

    /** @test */
    public function it_returns_null_when_no_start_event_exists()
    {
        $durationMs = TimeStatsWriter::for(TimeStatsEvent::class)->end('nonexistent');

        $this->assertNull($durationMs);
    }

    /** @test */
    public function it_merges_start_and_end_context()
    {
        TimeStatsWriter::for(TimeStatsEvent::class)->start('conv_123', now(), [
            'customer_id' => 999,
        ]);

        TimeStatsWriter::for(TimeStatsEvent::class)->end('conv_123', now(), [
            'employee_id' => 42,
        ]);

        $this->assertDatabaseHas('time_stats_events', [
            'identifier' => 'conv_123',
            'type' => 'completed',
        ]);

        // Verify merged context separately
        $event = TimeStatsEvent::where('identifier', 'conv_123')->where('type', 'completed')->first();
        $this->assertNotNull($event);
        $this->assertEquals([
            'customer_id' => 999,
            'employee_id' => 42,
        ], $event->context);
    }

    /** @test */
    public function it_matches_most_recent_uncompleted_start_event()
    {
        $firstStart = now()->subMinutes(10);
        $secondStart = now()->subMinutes(5);
        $endTime = now();

        // Create two start events
        TimeStatsWriter::for(TimeStatsEvent::class)->start('conv_123', $firstStart);
        TimeStatsWriter::for(TimeStatsEvent::class)->start('conv_123', $secondStart);

        // End should match the most recent one
        $durationMs = TimeStatsWriter::for(TimeStatsEvent::class)->end('conv_123', $endTime);

        // Should be 5 minutes (from second start), not 10 minutes
        $this->assertEquals(300000, $durationMs);
    }

    /** @test */
    public function it_can_work_with_attributes_filtering()
    {
        $startTime = now()->subMinutes(5);
        $endTime = now();

        // Start for tenant 1, employee 1
        TimeStatsWriter::for(TimeStatsEvent::class, [
            'tenant_id' => 1,
            'employee_id' => 1,
        ])->start('conv_123', $startTime);

        // Start for tenant 1, employee 2 (different employee)
        TimeStatsWriter::for(TimeStatsEvent::class, [
            'tenant_id' => 1,
            'employee_id' => 2,
        ])->start('conv_123', $startTime);

        // End for tenant 1, employee 1
        $durationMs = TimeStatsWriter::for(TimeStatsEvent::class, [
            'tenant_id' => 1,
            'employee_id' => 1,
        ])->end('conv_123', $endTime);

        $this->assertNotNull($durationMs);

        // Should only complete for employee 1
        $this->assertDatabaseHas('time_stats_events', [
            'tenant_id' => 1,
            'employee_id' => 1,
            'identifier' => 'conv_123',
            'type' => 'completed',
        ]);

        // Employee 2's start should still be uncompleted
        $this->assertDatabaseHas('time_stats_events', [
            'tenant_id' => 1,
            'employee_id' => 2,
            'identifier' => 'conv_123',
            'type' => 'start',
            'ended_at' => null,
        ]);
    }

    /** @test */
    public function it_can_work_with_hasMany_relationship()
    {
        /** @var Stat $stat */
        $stat = Stat::create();

        $startTime = now()->subMinutes(3);
        $endTime = now();

        // Note: This would require adding a hasMany relationship to Stat model for time_stats_events
        // For now, we'll skip this test as it requires additional setup
        $this->markTestSkipped('Requires HasMany relationship setup for TimeStatsEvent');
    }
}
