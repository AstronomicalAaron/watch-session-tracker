<?php

declare(strict_types=1);

namespace App\Tests\Functional\Module\ViewerEventIngestion;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ViewerEventIngestionTest extends WebTestCase
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

    public function testItAcceptsAValidViewerEvent(): void
    {
        $payload = [
            'sessionId' => 'abc-123',
            'userId' => 'user-456',
            'eventType' => 'start',
            'eventId' => 'evt-789',
            'eventTimestamp' => '2026-02-10T19:32:15.123Z',
            'receivedAt' => '2026-02-10T19:32:15.450Z',
            'payload' => [
                'eventId' => 'event-2026-wrestling-finals',
                'position' => 1832.5,
                'quality' => '1080p',
            ],
        ];

        $this->client->request(
            'POST',
            '/api/viewer-events',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM watch_session_events WHERE event_row_id = ?',
            ['evt-789']
        );

        $this->assertSame(1, $count);

        $sessionCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM watch_sessions WHERE session_id = ?',
            ['abc-123']
        );

        $this->assertSame(1, $sessionCount);
    }

    public function testItRejectsInvalidPayload(): void
    {
        $payload = [
            'sessionId' => 'abc-123',
            // missing userId, eventType, etc.
        ];

        $this->client->request(
            'POST',
            '/api/viewer-events',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(400); // or 422 depending on your implementation
    }

    public function testItHandlesDuplicateEventsIdempotently(): void
    {
        $payload = [
            'sessionId' => 'abc-123',
            'userId' => 'user-456',
            'eventType' => 'heartbeat',
            'eventId' => 'evt-dup-001',
            'eventTimestamp' => '2026-02-10T19:32:15.123Z',
            'receivedAt' => '2026-02-10T19:32:15.450Z',
            'payload' => [
                'eventId' => 'event-2026-wrestling-finals',
                'position' => 1832.5,
                'quality' => '1080p',
            ],
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/viewer-events', [], [], ['CONTENT_TYPE' => 'application/json'], $json);
        $this->assertResponseIsSuccessful();

        $this->client->request('POST', '/api/viewer-events', [], [], ['CONTENT_TYPE' => 'application/json'], $json);

        // If duplicate events are treated as success/idempotent:
        $this->assertTrue(
            $this->client->getResponse()->isSuccessful() || $this->client->getResponse()->getStatusCode() === 409
        );

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM watch_session_events WHERE event_row_id = ?',
            ['evt-dup-001']
        );

        $this->assertSame(1, $count);
    }
}
