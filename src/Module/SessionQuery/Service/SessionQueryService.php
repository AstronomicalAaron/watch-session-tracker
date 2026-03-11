<?php

declare(strict_types=1);

namespace App\Module\SessionQuery\Service;

use App\Repository\WatchSessionEventRepository;
use App\Repository\WatchSessionRepository;
use Doctrine\DBAL\Exception;

class SessionQueryService
{
    public function __construct(
        private readonly WatchSessionRepository $watchSessionRepository,
        private readonly WatchSessionEventRepository $watchSessionEventRepository,
    ) {
    }

    /**
     * @param string $sessionId
     * @return array
     * @throws Exception
     */
    public function getSessionDetails(string $sessionId): array
    {
        $session = $this->watchSessionRepository->findBySessionId($sessionId);

        if ($session === null) {
            throw new \RuntimeException('Session not found');
        }

        $events = $this->watchSessionEventRepository->findBySessionId($sessionId);

        return [
            'sessionId' => $session['session_id'],
            'userId' => $session['user_id'],
            'eventId' => $session['event_id'],
            'currentState' => $session['current_state'],
            'startedAt' => $session['started_at'],
            'lastEventAt' => $session['last_event_at'],
            'lastReceivedAt' => $session['last_received_at'],
            'durationSoFarSeconds' => (int) $session['total_watch_seconds'],
            'eventsReceived' => (int) $session['event_count'],
            'lastPosition' => $session['last_position'],
            'lastQuality' => $session['last_quality'],
            'events' => $events,
        ];
    }
}
