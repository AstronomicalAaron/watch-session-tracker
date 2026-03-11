<?php

declare(strict_types=1);

namespace App\Tests\Functional\Module\SessionQuery;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SessionDetailsTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);

        $this->connection->executeStatement('DELETE FROM watch_session_events');
        $this->connection->executeStatement('DELETE FROM watch_sessions');
    }

    public function testItReturnsSessionDetails(): void
    {
        $this->connection->insert('watch_sessions', [
            'session_id' => 'abc-123',
            'user_id' => 'user-456',
            'event_id' => 'event-2026-wrestling-finals',
            'current_state' => 'active',
            'started_at' => '2026-02-10T19:32:15.123Z',
            'last_event_at' => '2026-02-10T19:32:15.123Z',
            'last_received_at' => '2026-02-10T19:32:15.123Z',
            'last_position' => 60.0,
            'last_quality' => '1080p',
            'event_count' => 2,
            'total_watch_seconds' => 60,
            'created_at' => '2026-02-10T19:32:15.123Z',
            'updated_at' => '2026-02-10T19:32:15.123Z',
        ]);

        $this->connection->insert('watch_session_events', [
            'event_row_id' => 'evt-1',
            'session_id' => 'abc-123',
            'user_id' => 'user-456',
            'event_type' => 'start',
            'sdk_event_timestamp' => '2026-02-10T19:32:15.123Z',
            'received_at' => '2026-02-10T19:32:15.123Z',
            'stream_event_id' => 'event-2026-wrestling-finals',
            'position' => 0.0,
            'quality' => '1080p',
            'raw_payload' => json_encode([
                'sessionId' => 'abc-123',
                'userId' => 'user-456',
                'eventType' => 'start',
                'eventId' => 'evt-1',
                'eventTimestamp' => '2026-02-10T19:32:15.123Z',
                'receivedAt' => '2026-02-10T19:32:15.123Z',
                'payload' => [
                    'eventId' => 'event-2026-wrestling-finals',
                    'position' => 0,
                    'quality' => '1080p',
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-10T19:32:15.123Z',
        ]);

        $this->connection->insert('watch_session_events', [
            'event_row_id' => 'evt-2',
            'session_id' => 'abc-123',
            'user_id' => 'user-456',
            'event_type' => 'heartbeat',
            'sdk_event_timestamp' => '2026-02-10T19:32:15.123Z',
            'received_at' => '2026-02-10T19:32:15.123Z',
            'stream_event_id' => 'event-2026-wrestling-finals',
            'position' => 60.0,
            'quality' => '1080p',
            'raw_payload' => json_encode([
                'sessionId' => 'abc-123',
                'userId' => 'user-456',
                'eventType' => 'heartbeat',
                'eventId' => 'evt-2',
                'eventTimestamp' => '2026-02-10T19:32:15.123Z',
                'receivedAt' => '2026-02-10T19:32:15.123Z',
                'payload' => [
                    'eventId' => 'event-2026-wrestling-finals',
                    'position' => 60,
                    'quality' => '1080p',
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-10T19:32:15.123Z',
        ]);

        $this->client->request('GET', '/api/sessions/abc-123');

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('abc-123', $data['sessionId']);
        $this->assertSame('user-456', $data['userId']);
        $this->assertSame('event-2026-wrestling-finals', $data['eventId']);
        $this->assertArrayHasKey('durationSoFarSeconds', $data);
        $this->assertArrayHasKey('currentState', $data);
        $this->assertArrayHasKey('events', $data);
        $this->assertCount(2, $data['events']);
    }

    public function testItReturns404ForUnknownSession(): void
    {
        $this->client->request('GET', '/api/sessions/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }
}
