<?php

namespace Spatie\Stats;

use DateTimeInterface;
use Spatie\Stats\Models\TimeStatsEvent;

abstract class BaseTimeStats
{
    public function getName(): string
    {
        return class_basename($this);
    }

    public static function query(): TimeStatsQuery
    {
        return TimeStatsQuery::for(TimeStatsEvent::class, [
            'name' => (new static)->getName(),
        ]);
    }

    public static function writer(): TimeStatsWriter
    {
        return TimeStatsWriter::for(TimeStatsEvent::class, [
            'name' => (new static)->getName(),
        ]);
    }

    /**
     * Start timing an event
     *
     * @param string $identifier Unique identifier for this timing event (e.g., conversation_id, session_id)
     * @param DateTimeInterface|null $timestamp When the event started (defaults to now)
     * @param array $context Additional context data to store
     * @return void
     */
    public static function start(string $identifier, ?DateTimeInterface $timestamp = null, array $context = [])
    {
        static::writer()->start($identifier, $timestamp, $context);
    }

    /**
     * End timing an event and record the duration
     *
     * @param string $identifier The same identifier used in start()
     * @param DateTimeInterface|null $timestamp When the event ended (defaults to now)
     * @param array $context Additional context data to store
     * @return int|null The duration in milliseconds, or null if no start event found
     */
    public static function end(string $identifier, ?DateTimeInterface $timestamp = null, array $context = [])
    {
        return static::writer()->end($identifier, $timestamp, $context);
    }

    /**
     * Record a duration directly without start/end tracking
     *
     * @param int $durationMs Duration in milliseconds
     * @param DateTimeInterface|null $timestamp When this duration was recorded (defaults to now)
     * @param array $context Additional context data to store
     * @return void
     */
    public static function record(int $durationMs, ?DateTimeInterface $timestamp = null, array $context = [])
    {
        static::writer()->record($durationMs, $timestamp, $context);
    }
}
