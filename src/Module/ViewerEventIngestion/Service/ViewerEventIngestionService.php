<?php

declare(strict_types=1);

namespace App\Module\ViewerEventIngestion\Service;

use App\Psr\SystemClock;
use App\Repository\WatchSessionEventRepository;
use App\Repository\WatchSessionRepository;
use Doctrine\DBAL\Exception;

class ViewerEventIngestionService
{
    public function __construct(
        private readonly WatchSessionRepository $watchSessionRepository,
        private readonly WatchSessionEventRepository $watchSessionEventRepository,
        private readonly SystemClock $systemClock,
    ) {
    }

    /**
     * @param array<string, mixed> $event
     * @return string[] - returns an array with the status and session id if the event was accepted,
     * or an array with the status and session id if the event was ignored
     * @throws Exception - will throw an exception if the query fails
     * @throws \JsonException|\DateMalformedStringException - will throw an exception if the payload cannot be JSON encoded
     */
    public function ingest(array $event): array
    {
        $this->validate($event);

        $eventData = $this->buildEventData($event);

        $inserted = $this->watchSessionEventRepository->insertIfNotExists($eventData);

        // If the event is a duplicate, we ignore it and return a status indicating that it was ignored
        // Also saves us from having to do later queries
        if (! $inserted) {
            return [
                'status' => 'duplicate_ignored',
                'sessionId' => (string) $event['sessionId'],
                'eventId' => (string) $event['eventId'],
            ];
        }

        // If the event is not a duplicate, we try to find an existing session and update it
        $existingSession = $this->watchSessionRepository->findBySessionId((string) $event['sessionId']);

        if ($existingSession === null) {
            // If no existing session was found, we create a new one
            $snapshot = $this->buildNewSessionSnapshot($event);
            $this->watchSessionRepository->insert($snapshot);
        } else {
            // If an existing session was found, we update it
            $snapshot = $this->buildUpdatedSessionSnapshot($existingSession, $event);
            $this->watchSessionRepository->update((string) $event['sessionId'], $snapshot);
        }

        return [
            'status' => 'accepted',
            'sessionId' => (string) $event['sessionId'],
            'eventId' => (string) $event['eventId'],
        ];
    }

    /**
     * @param array<string, mixed> $event
     */
    private function validate(array $event): void
    {
        $requiredFields = [
            'sessionId',
            'userId',
            'eventType',
            'eventId',
            'eventTimestamp',
            'receivedAt',
            'payload',
        ];

        // Make sure all required fields are present
        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $event)) {
                throw new \RuntimeException(sprintf('Missing required field: %s', $field));
            }
        }

        // Make sure payload is an array
        if (! is_array($event['payload'])) {
            throw new \RuntimeException('Field "payload" must be an array');
        }

        if (! array_key_exists('eventId', $event['payload'])) {
            throw new \RuntimeException('Missing required field: payload.eventId');
        }

        $allowedEventTypes = [
            'start',
            'heartbeat',
            'pause',
            'resume',
            'seek',
            'quality_change',
            'buffer_start',
            'buffer_end',
            'end',
        ];

        if (! in_array($event['eventType'], $allowedEventTypes, true)) {
            throw new \RuntimeException(sprintf(
                'Unsupported event type: %s',
                $event['eventType']
            ));
        }

        $this->parseTimestamp((string) $event['eventTimestamp'], 'eventTimestamp');
        $this->parseTimestamp((string) $event['receivedAt'], 'receivedAt');
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed> - event data
     * @throws \JsonException|\DateMalformedStringException
     */
    private function buildEventData(array $event): array
    {
        return [
            'event_row_id' => (string) $event['eventId'],
            'session_id' => (string) $event['sessionId'],
            'user_id' => (string) $event['userId'],
            'event_type' => (string) $event['eventType'],
            'sdk_event_timestamp' => (string) $event['eventTimestamp'],
            'received_at' => (string) $event['receivedAt'],
            'stream_event_id' => (string) $event['payload']['eventId'],
            'position' => isset($event['payload']['position']) ? (float) $event['payload']['position'] : null,
            'quality' => isset($event['payload']['quality']) ? (string) $event['payload']['quality'] : null,
            'raw_payload' => json_encode($event, JSON_THROW_ON_ERROR),
            'created_at' => $this->systemClock->now()->format(SystemClock::DATE_FORMAT),
        ];
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed> - session snapshot
     * @throws \DateMalformedStringException
     */
    private function buildNewSessionSnapshot(array $event): array
    {
        $eventTimestamp = $this->parseTimestamp((string) $event['eventTimestamp'], 'eventTimestamp');
        $receivedAt = $this->parseTimestamp((string) $event['receivedAt'], 'receivedAt');
        $now = $this->systemClock->now();

        return [
            'session_id' => (string) $event['sessionId'],
            'user_id' => (string) $event['userId'],
            'event_id' => (string) $event['payload']['eventId'],
            'current_state' => $this->resolveState((string) $event['eventType'], null),
            'started_at' => $eventTimestamp->format(SystemClock::DATE_FORMAT),
            'last_event_at' => $eventTimestamp->format(SystemClock::DATE_FORMAT),
            'last_received_at' => $receivedAt->format(SystemClock::DATE_FORMAT),
            'last_position' => isset($event['payload']['position']) ? (float) $event['payload']['position'] : null,
            'last_quality' => isset($event['payload']['quality']) ? (string) $event['payload']['quality'] : null,
            'event_count' => 1,
            'total_watch_seconds' => 0,
            'created_at' => $now->format(SystemClock::DATE_FORMAT),
            'updated_at' => $now->format(SystemClock::DATE_FORMAT),
        ];
    }

    /**
     * @param array<string, mixed> $existingSession
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     * @throws \DateMalformedStringException
     */
    private function buildUpdatedSessionSnapshot(array $existingSession, array $event): array
    {
        $incomingEventAt = $this->parseTimestamp((string) $event['eventTimestamp'], 'eventTimestamp');
        $incomingReceivedAt = $this->parseTimestamp((string) $event['receivedAt'], 'receivedAt');
        $storedLastEventAt = $this->parseTimestamp((string) $existingSession['last_event_at'], 'last_event_at');
        $startedAt = $this->parseTimestamp((string) $existingSession['started_at'], 'started_at');
        $now = $this->systemClock->now();

        $isNewerOrSame = $incomingEventAt >= $storedLastEventAt;

        $lastEventAt = $isNewerOrSame ? $incomingEventAt : $storedLastEventAt;

        // Update the last received at timestamp based on the incoming received at timestamp
        $lastReceivedAt = $isNewerOrSame
            ? $incomingReceivedAt
            : $this->parseTimestamp((string) $existingSession['last_received_at'], 'last_received_at');

        // Update the current state based on the event type and the existing state
        $currentState = $isNewerOrSame
            ? $this->resolveState((string) $event['eventType'], (string) $existingSession['current_state'])
            : (string) $existingSession['current_state'];

        // Update the position based on the event type and the existing position
        $lastPosition = $isNewerOrSame
            ? (isset($event['payload']['position']) ? (float) $event['payload']['position'] : $existingSession['last_position'])
            : $existingSession['last_position'];

        // Update the quality based on the event type and the existing quality
        $lastQuality = $isNewerOrSame
            ? (isset($event['payload']['quality']) ? (string) $event['payload']['quality'] : $existingSession['last_quality'])
            : $existingSession['last_quality'];

        // Calculate the duration so far based on the last event at timestamp and the started at timestamp
        $durationSoFar = max(
            0,
            // Here we actually use the unix timestamp to perform the calculation
            $lastEventAt->getTimestamp() - $startedAt->getTimestamp()
        );

        return [
            'session_id' => (string) $existingSession['session_id'],
            'user_id' => (string) $event['userId'],
            'event_id' => (string) $existingSession['event_id'],
            'current_state' => $currentState,
            'started_at' => $startedAt->format(SystemClock::DATE_FORMAT),
            'last_event_at' => $lastEventAt->format(SystemClock::DATE_FORMAT),
            'last_received_at' => $lastReceivedAt->format(SystemClock::DATE_FORMAT),
            'last_position' => $lastPosition,
            'last_quality' => $lastQuality,
            'event_count' => ((int) $existingSession['event_count']) + 1,
            'total_watch_seconds' => $durationSoFar,
            'updated_at' => $now->format(SystemClock::DATE_FORMAT),
            'created_at' => (string) $existingSession['created_at'],
        ];
    }

    private function resolveState(string $eventType, ?string $currentState): string
    {
        return match ($eventType) {
            'start', 'resume', 'seek', 'buffer_end' => 'active',
            'pause' => 'paused',
            'buffer_start' => 'buffering',
            'end' => 'ended',
            'quality_change' => $currentState ?? 'active',
            'heartbeat' => $currentState === 'ended' ? 'ended' : ($currentState ?? 'active'),
            default => throw new \RuntimeException(sprintf('Unsupported event type: %s', $eventType)),
        };
    }

    /**
     * Will parse a timestamp string and return a DateTimeImmutable object
     * If the timestamp is invalid, it will throw a RuntimeException
     *
     * @param string $value
     * @param string $fieldName
     * @return \DateTimeImmutable
     */
    private function parseTimestamp(string $value, string $fieldName): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new \RuntimeException(sprintf('Invalid timestamp for field: %s', $fieldName));
        }
    }
}
