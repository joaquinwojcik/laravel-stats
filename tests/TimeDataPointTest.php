<?php

namespace Spatie\Stats\Tests;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Spatie\Stats\TimeDataPoint;

class TimeDataPointTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated()
    {
        $dataPoint = new TimeDataPoint(
            start: Carbon::parse('2020-01-01 00:00:00'),
            end: Carbon::parse('2020-01-02 00:00:00'),
            count: 10,
            totalDurationMs: 600000,
            averageDurationMs: 60000,
            minDurationMs: 30000,
            maxDurationMs: 120000,
            averageSeconds: 60.0,
            averageMinutes: 1.0,
        );

        $this->assertInstanceOf(TimeDataPoint::class, $dataPoint);
    }

    /** @test */
    public function it_has_correct_properties()
    {
        $start = Carbon::parse('2020-01-01 00:00:00');
        $end = Carbon::parse('2020-01-02 00:00:00');

        $dataPoint = new TimeDataPoint(
            start: $start,
            end: $end,
            count: 10,
            totalDurationMs: 600000,
            averageDurationMs: 60000,
            minDurationMs: 30000,
            maxDurationMs: 120000,
            averageSeconds: 60.0,
            averageMinutes: 1.0,
        );

        $this->assertEquals($start, $dataPoint->start);
        $this->assertEquals($end, $dataPoint->end);
        $this->assertEquals(10, $dataPoint->count);
        $this->assertEquals(600000, $dataPoint->totalDurationMs);
        $this->assertEquals(60000, $dataPoint->averageDurationMs);
        $this->assertEquals(30000, $dataPoint->minDurationMs);
        $this->assertEquals(120000, $dataPoint->maxDurationMs);
        $this->assertEquals(60.0, $dataPoint->averageSeconds);
        $this->assertEquals(1.0, $dataPoint->averageMinutes);
    }

    /** @test */
    public function it_can_be_converted_to_array()
    {
        $start = Carbon::parse('2020-01-01 00:00:00');
        $end = Carbon::parse('2020-01-02 00:00:00');

        $dataPoint = new TimeDataPoint(
            start: $start,
            end: $end,
            count: 10,
            totalDurationMs: 600000,
            averageDurationMs: 60000,
            minDurationMs: 30000,
            maxDurationMs: 120000,
            averageSeconds: 60.0,
            averageMinutes: 1.0,
        );

        $array = $dataPoint->toArray();

        $this->assertIsArray($array);
        $this->assertEquals($start->toIso8601String(), $array['start']);
        $this->assertEquals($end->toIso8601String(), $array['end']);
        $this->assertEquals(10, $array['count']);
        $this->assertEquals(600000, $array['total_duration_ms']);
        $this->assertEquals(60000, $array['average_duration_ms']);
        $this->assertEquals(30000, $array['min_duration_ms']);
        $this->assertEquals(120000, $array['max_duration_ms']);
        $this->assertEquals(60.0, $array['average_seconds']);
        $this->assertEquals(1.0, $array['average_minutes']);
    }

    /** @test */
    public function it_can_get_average_duration_in_seconds()
    {
        $dataPoint = new TimeDataPoint(
            start: Carbon::parse('2020-01-01'),
            end: Carbon::parse('2020-01-02'),
            count: 10,
            totalDurationMs: 600000,
            averageDurationMs: 75500, // 75.5 seconds
            minDurationMs: 30000,
            maxDurationMs: 120000,
            averageSeconds: 0,
            averageMinutes: 0,
        );

        $this->assertEquals(75.5, $dataPoint->getAverageDurationInSeconds());
    }

    /** @test */
    public function it_can_get_average_duration_in_minutes()
    {
        $dataPoint = new TimeDataPoint(
            start: Carbon::parse('2020-01-01'),
            end: Carbon::parse('2020-01-02'),
            count: 10,
            totalDurationMs: 600000,
            averageDurationMs: 150000, // 2.5 minutes
            minDurationMs: 30000,
            maxDurationMs: 120000,
            averageSeconds: 0,
            averageMinutes: 0,
        );

        $this->assertEquals(2.5, $dataPoint->getAverageDurationInMinutes());
    }

    /** @test */
    public function it_can_get_total_duration_in_seconds()
    {
        $dataPoint = new TimeDataPoint(
            start: Carbon::parse('2020-01-01'),
            end: Carbon::parse('2020-01-02'),
            count: 10,
            totalDurationMs: 125500, // 125.5 seconds
            averageDurationMs: 60000,
            minDurationMs: 30000,
            maxDurationMs: 120000,
            averageSeconds: 0,
            averageMinutes: 0,
        );

        $this->assertEquals(125.5, $dataPoint->getTotalDurationInSeconds());
    }

    /** @test */
    public function it_can_get_total_duration_in_minutes()
    {
        $dataPoint = new TimeDataPoint(
            start: Carbon::parse('2020-01-01'),
            end: Carbon::parse('2020-01-02'),
            count: 10,
            totalDurationMs: 450000, // 7.5 minutes
            averageDurationMs: 60000,
            minDurationMs: 30000,
            maxDurationMs: 120000,
            averageSeconds: 0,
            averageMinutes: 0,
        );

        $this->assertEquals(7.5, $dataPoint->getTotalDurationInMinutes());
    }

    /** @test */
    public function it_rounds_duration_conversions_correctly()
    {
        $dataPoint = new TimeDataPoint(
            start: Carbon::parse('2020-01-01'),
            end: Carbon::parse('2020-01-02'),
            count: 3,
            totalDurationMs: 100000,
            averageDurationMs: 33333, // 33.333 seconds
            minDurationMs: 30000,
            maxDurationMs: 40000,
            averageSeconds: 0,
            averageMinutes: 0,
        );

        $this->assertEquals(33.33, $dataPoint->getAverageDurationInSeconds());
        $this->assertEquals(0.56, $dataPoint->getAverageDurationInMinutes()); // 33.33 / 60 = 0.5555 -> 0.56
    }

    /** @test */
    public function it_handles_zero_values()
    {
        $dataPoint = new TimeDataPoint(
            start: Carbon::parse('2020-01-01'),
            end: Carbon::parse('2020-01-02'),
            count: 0,
            totalDurationMs: 0,
            averageDurationMs: 0,
            minDurationMs: 0,
            maxDurationMs: 0,
            averageSeconds: 0.0,
            averageMinutes: 0.0,
        );

        $this->assertEquals(0, $dataPoint->getAverageDurationInSeconds());
        $this->assertEquals(0, $dataPoint->getAverageDurationInMinutes());
        $this->assertEquals(0, $dataPoint->getTotalDurationInSeconds());
        $this->assertEquals(0, $dataPoint->getTotalDurationInMinutes());
    }
}
