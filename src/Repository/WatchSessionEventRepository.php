<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class WatchSessionEventRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Returns true if inserted, false if duplicate.
     * TODO - create a WatchSessionEvent entity and use the entity manager to insert the event
     *
     * @param array<string, mixed> $data - the data to insert
     * @return bool - true if inserted, false if duplicate
     * @throws Exception - will throw an exception if the query fails
     */
    public function insertIfNotExists(array $data): bool
    {
        try {
            $this->connection->insert('watch_session_events', [
                'event_row_id' => $data['event_row_id'],
                'session_id' => $data['session_id'],
                'user_id' => $data['user_id'],
                'event_type' => $data['event_type'],
                'sdk_event_timestamp' => $data['sdk_event_timestamp'],
                'received_at' => $data['received_at'],
                'stream_event_id' => $data['stream_event_id'],
                'position' => $data['position'],
                'quality' => $data['quality'],
                'raw_payload' => $data['raw_payload'],
                'created_at' => $data['created_at'],
            ]);

            return true;
        } catch (Exception $exception) {
            if (str_contains($exception->getMessage(), 'UNIQUE constraint failed')) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Finds all events for a given session id.
     * TODO - create a WatchSessionEvent entity and use the entity manager to find all events for a given session id
     * TODO - create a WatchSessionEventCollection entity and have this return a WatchSessionEventCollection
     *
     * @param string $sessionId - the id of the watch session
     * @return array<int, array<string, mixed>> - an array of events
     * @throws Exception - will throw an exception if the query fails
     */
    public function findBySessionId(string $sessionId): array
    {
        $sql = <<<'SQL'
            SELECT
                event_row_id,
                event_type,
                sdk_event_timestamp,
                received_at,
                position,
                quality
            FROM watch_session_events
            WHERE session_id = :sessionId
            ORDER BY sdk_event_timestamp ASC, event_row_id ASC
        SQL;

        // TODO - create WatchSessionEventCollection, hydrate it with this data, and return
        return $this->connection->fetchAllAssociative($sql, [
            'sessionId' => $sessionId,
        ]);
    }
}
