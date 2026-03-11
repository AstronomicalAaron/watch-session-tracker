<?php

declare(strict_types=1);

namespace App\Repository;

use App\Psr\SystemClock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class WatchSessionRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Will fetch a watch session record by its session id
     * TODO - Create a WatchSession entity and use the entity manager to fetch the record lazily and then eagerly load when necessary
     *
     * @param string $sessionId - the id of the watch session
     * @return array|null - will return null if the record is not found
     * @throws Exception - will throw an exception if the query fails
     */
    public function findBySessionId(string $sessionId): ?array
    {
        $sql = <<<'SQL'
            SELECT
                session_id,
                user_id,
                event_id,
                current_state,
                started_at,
                last_event_at,
                last_received_at,
                last_position,
                last_quality,
                event_count,
                total_watch_seconds,
                created_at,
                updated_at
            FROM watch_sessions
            WHERE session_id = :sessionId
            LIMIT 1
        SQL;

        $session = $this->connection->fetchAssociative(
            $sql,
            ['sessionId' => $sessionId],
        );

        return $session === false ? null : $session;
    }

    /**
     * Will insert a new watch session record
     * TODO - Create a WatchSession entity and use the entity manager to insert the record
     *
     * @param array<string, mixed> $data - the data to insert into the watch_sessions table
     * @throws Exception - will throw an exception if the query fails
     */
    public function insert(array $data): void
    {
        $this->connection->insert('watch_sessions', [
            'session_id' => $data['session_id'],
            'user_id' => $data['user_id'],
            'event_id' => $data['event_id'],
            'current_state' => $data['current_state'],
            'started_at' => $data['started_at'],
            'last_event_at' => $data['last_event_at'],
            'last_received_at' => $data['last_received_at'],
            'last_position' => $data['last_position'],
            'last_quality' => $data['last_quality'],
            'event_count' => $data['event_count'],
            'total_watch_seconds' => $data['total_watch_seconds'],
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at'],
        ]);
    }

    /**
     * Will update an existing watch session record
     * TODO - Create a WatchSession entity and use the entity manager to update the record
     *
     * @param string $sessionId - the id of the watch session
     * @param array<string, mixed> $data - the data to update the record with
     * @throws Exception
     */
    public function update(string $sessionId, array $data): void
    {
        $this->connection->update(
            'watch_sessions',
            [
                'user_id' => $data['user_id'],
                'event_id' => $data['event_id'],
                'current_state' => $data['current_state'],
                'started_at' => $data['started_at'],
                'last_event_at' => $data['last_event_at'],
                'last_received_at' => $data['last_received_at'],
                'last_position' => $data['last_position'],
                'last_quality' => $data['last_quality'],
                'event_count' => $data['event_count'],
                'total_watch_seconds' => $data['total_watch_seconds'],
                'updated_at' => $data['updated_at'],
            ],
            [
                'session_id' => $sessionId,
            ]
        );
    }

    /**
     * Will insert a new or update an existing watch session record
     * TODO - Create a WatchSession entity and use the entity manager to insert or update the record
     *
     * @param array<string, mixed> $data
     * @throws Exception - will throw an exception if the query fails
     */
    public function upsert(array $data): void
    {
        $existing = $this->findBySessionId($data['session_id']);

        if ($existing === null) {
            $this->insert($data);
            return;
        }

        $this->update($data['session_id'], $data);
    }

    /**
     * Counts the number of active watch sessions for a given event id
     * TODO - Create a WatchSession entity and use the entity manager to count the number of active watch sessions
     *
     * @param string $eventId - the id of the event
     * @param \DateTimeImmutable $activeThreshold - the threshold for determining if a watch session is active
     * @return int - the number of active watch sessions for the given event id
     * @throws Exception - will throw an exception if the query fails
     */
    public function countActiveByEventId(
        string $eventId,
        \DateTimeImmutable $activeThreshold,
    ): int {
        $sql = <<<'SQL'
        SELECT COUNT(*)
        FROM watch_sessions
        WHERE event_id = :eventId
          AND current_state IN ('active', 'buffering')
          AND datetime(last_event_at) >= datetime(:activeThreshold)
    SQL;

        return (int)$this->connection->fetchOne($sql, [
            'eventId' => $eventId,
            'activeThreshold' => $activeThreshold->format(SystemClock::DATE_FORMAT),
        ]);
    }
}
