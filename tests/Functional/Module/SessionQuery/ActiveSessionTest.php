<?php

declare(strict_types=1);

namespace App\Tests\Functional\Module\SessionQuery;

use App\Psr\SystemClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ActiveSessionTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;
    private SystemClock $systemClock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->systemClock = new SystemClock();

        $this->connection->executeStatement('DELETE FROM watch_session_events');
        $this->connection->executeStatement('DELETE FROM watch_sessions');
    }

    public function testItReturnsActiveSessionCountForAnEvent(): void
    {
        $recent1 = $this->systemClock->now()->modify('-10 seconds')->format(SystemClock::DATE_FORMAT);
        $recent2 = $this->systemClock->now()->modify('-20 seconds')->format(SystemClock::DATE_FORMAT);

        $this->insertSession('session-1', 'user-1', 'event-123', 'active', $recent1, $recent1, $recent1);
        $this->insertSession('session-2', 'user-2', 'event-123', 'active', $recent2, $recent2, $recent2);

        $this->client->request('GET', '/api/events/event-123/active-sessions');

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('event-123', $data['eventId']);
        $this->assertSame(2, $data['activeSessionCount']);
    }

    public function testItExcludesInactiveSessionsOutsideThe45SecondWindow(): void
    {
        $recent = $this->systemClock->now()->modify('-10 seconds')->format(SystemClock::DATE_FORMAT);
        $stale = $this->systemClock->now()->modify('-90 seconds')->format(SystemClock::DATE_FORMAT);

        $this->insertSession('session-active', 'user-1', 'event-123', 'active', $recent, $recent, $recent);
        $this->insertSession('session-stale', 'user-2', 'event-123', 'active', $stale, $stale, $stale);

        $this->client->request('GET', '/api/events/event-123/active-sessions');

        $this->assertResponseIsSuccessful();

        $data = json_decode(
            $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame(1, $data['activeSessionCount']);
    }

    private function insertSession(
        string $sessionId,
        string $userId,
        string $eventId,
        string $currentState = 'active',
        ?string $startedAt = null,
        ?string $lastEventAt = null,
        ?string $lastReceivedAt = null,
    ): void {
        $startedAt ??= '2026-02-10T19:32:00+00:00';
        $lastEventAt ??= $startedAt;
        $lastReceivedAt ??= $lastEventAt;

        $this->connection->insert('watch_sessions', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'event_id' => $eventId,
            'current_state' => $currentState,
            'started_at' => $startedAt,
            'last_event_at' => $lastEventAt,
            'last_received_at' => $lastReceivedAt,
            'last_position' => 10.0,
            'last_quality' => '1080p',
            'event_count' => 1,
            'total_watch_seconds' => 0,
            'created_at' => $startedAt,
            'updated_at' => $lastReceivedAt,
        ]);
    }
}
