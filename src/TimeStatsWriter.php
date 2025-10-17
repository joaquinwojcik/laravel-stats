<?php

namespace Spatie\Stats;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class TimeStatsWriter
{
    private Model|Relation|string $subject;
    private array $attributes;

    public function __construct(Model|Relation|string $subject, array $attributes = [])
    {
        $this->subject = $subject;
        $this->attributes = $attributes;
    }

    public static function for(Model|Relation|string $subject, array $attributes = [])
    {
        return new static($subject, $attributes);
    }

    /**
     * Start timing an event
     *
     * @param string $identifier Unique identifier for this timing event
     * @param DateTimeInterface|null $timestamp When the event started
     * @param array $context Additional context data
     * @return Model
     */
    public function start(string $identifier, ?DateTimeInterface $timestamp = null, array $context = []): Model
    {
        return $this->createEvent(
            type: TimeDataPoint::TYPE_START,
            identifier: $identifier,
            startedAt: $timestamp ?? now(),
            endedAt: null,
            durationMs: null,
            context: $context
        );
    }

    /**
     * End timing an event and record the duration
     *
     * @param string $identifier The same identifier used in start()
     * @param DateTimeInterface|null $timestamp When the event ended
     * @param array $context Additional context data
     * @return int|null The duration in milliseconds, or null if no start event found
     */
    public function end(string $identifier, ?DateTimeInterface $timestamp = null, array $context = []): ?int
    {
        $endTimestamp = $timestamp ?? now();

        // Find the most recent start event for this identifier
        $startEvent = $this->queryStats()
            ->where('type', TimeDataPoint::TYPE_START)
            ->where('identifier', $identifier)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();

        if (!$startEvent) {
            return null;
        }

        // Calculate duration in milliseconds
        $durationMs = (int) ($endTimestamp->valueOf() - $startEvent->started_at->valueOf());

        // Create the completed event
        $this->createEvent(
            type: TimeDataPoint::TYPE_COMPLETED,
            identifier: $identifier,
            startedAt: $startEvent->started_at,
            endedAt: $endTimestamp,
            durationMs: $durationMs,
            context: array_merge($startEvent->context ?? [], $context)
        );

        // Mark the start event as completed by setting ended_at
        $startEvent->update(['ended_at' => $endTimestamp]);

        return $durationMs;
    }

    /**
     * Record a duration directly without start/end tracking
     *
     * @param int $durationMs Duration in milliseconds
     * @param DateTimeInterface|null $timestamp When this duration was recorded
     * @param array $context Additional context data
     * @return Model
     */
    public function record(int $durationMs, ?DateTimeInterface $timestamp = null, array $context = []): Model
    {
        $recordedAt = $timestamp ?? now();

        return $this->createEvent(
            type: TimeDataPoint::TYPE_COMPLETED,
            identifier: null,
            startedAt: $recordedAt,
            endedAt: $recordedAt,
            durationMs: $durationMs,
            context: $context
        );
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    protected function createEvent(
        string $type,
        ?string $identifier,
        DateTimeInterface $startedAt,
        ?DateTimeInterface $endedAt,
        ?int $durationMs,
        array $context = []
    ): Model {
        $data = array_merge($this->attributes, [
            'type' => $type,
            'identifier' => $identifier,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_ms' => $durationMs,
            'context' => !empty($context) ? $context : null,
            'created_at' => now(),
        ]);

        if ($this->subject instanceof Relation) {
            return $this->subject->create($data);
        }

        $subject = $this->subject;
        if ($subject instanceof Model) {
            $subject = get_class($subject);
        }

        return $subject::create($data);
    }

    protected function queryStats()
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
