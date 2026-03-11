<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\ViewerEventIngestion\Service;

use App\Module\ViewerEventIngestion\Service\ViewerEventIngestionService;
use App\Psr\SystemClock;
use App\Repository\WatchSessionEventRepository;
use App\Repository\WatchSessionRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ViewerEventIngestionServiceTest extends TestCase
{
    private WatchSessionRepository&MockObject $watchSessionRepository;
    private WatchSessionEventRepository&MockObject $watchSessionEventRepository;
    private SystemClock&MockObject $utcClock;

    private ViewerEventIngestionService $service;

    protected function setUp(): void
    {
        $this->watchSessionRepository = $this->createMock(WatchSessionRepository::class);
        $this->watchSessionEventRepository = $this->createMock(WatchSessionEventRepository::class);
        $this->utcClock = $this->createMock(SystemClock::class);

        $this->service = new ViewerEventIngestionService(
            $this->watchSessionRepository,
            $this->watchSessionEventRepository,
            $this->utcClock,
        );
    }

    public function testItAcceptsAValidNewEventAndCreatesASession(): void
    {
        $event = $this->validEvent([
            'eventType' => 'start',
            'eventTimestamp' => '2026-02-10T19:32:15.000Z',
            'receivedAt' => '2026-02-10T19:32:15.000Z',
        ]);

        $now = new \DateTimeImmutable('2026-02-10T19:32:15.000Z');

        $this->utcClock
            ->method('now')
            ->willReturn($now);

        $this->watchSessionEventRepository
            ->expects($this->once())
            ->method('insertIfNotExists')
            ->with(
                $this->callback(function (array $eventData): bool {
                    $this->assertSame('evt-789', $eventData['event_row_id']);
                    $this->assertSame('abc-123', $eventData['session_id']);
                    $this->assertSame('user-456', $eventData['user_id']);
                    $this->assertSame('start', $eventData['event_type']);
                    $this->assertSame('event-2026-wrestling-finals', $eventData['stream_event_id']);
                    $this->assertSame(1832.5, $eventData['position']);
                    $this->assertSame('1080p', $eventData['quality']);
                    $this->assertIsString($eventData['raw_payload']);
                    $this->assertSame('2026-02-10T19:32:15.000Z', $eventData['created_at']);

                    return true;
                })
            )
            ->willReturn(true);

        $this->watchSessionRepository
            ->expects($this->once())
            ->method('findBySessionId')
            ->with('abc-123')
            ->willReturn(null);

        $this->watchSessionRepository
            ->expects($this->once())
            ->method('insert')
            ->with(
                $this->callback(function (array $snapshot): bool {
                    $this->assertSame('abc-123', $snapshot['session_id']);
                    $this->assertSame('user-456', $snapshot['user_id']);
                    $this->assertSame('event-2026-wrestling-finals', $snapshot['event_id']);
                    $this->assertSame('active', $snapshot['current_state']);
                    $this->assertSame('2026-02-10T19:32:15.000Z', $snapshot['started_at']);
                    $this->assertSame('2026-02-10T19:32:15.000Z', $snapshot['last_event_at']);
                    $this->assertSame('2026-02-10T19:32:15.000Z', $snapshot['last_received_at']);
                    $this->assertSame(1832.5, $snapshot['last_position']);
                    $this->assertSame('1080p', $snapshot['last_quality']);
                    $this->assertSame(1, $snapshot['event_count']);
                    $this->assertSame(0, $snapshot['total_watch_seconds']);
                    $this->assertSame('2026-02-10T19:32:15.000Z', $snapshot['created_at']);
                    $this->assertSame('2026-02-10T19:32:15.000Z', $snapshot['updated_at']);

                    return true;
                })
            );

        $result = $this->service->ingest($event);

        $this->assertSame([
            'status' => 'accepted',
            'sessionId' => 'abc-123',
            'eventId' => 'evt-789',
        ], $result);
    }

    public function testItIgnoresDuplicateEvents(): void
    {
        $event = $this->validEvent();

        $this->utcClock
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-02-10T19:32:16.000Z'));

        $this->watchSessionEventRepository
            ->expects($this->once())
            ->method('insertIfNotExists')
            ->willReturn(false);

        $this->watchSessionRepository
            ->expects($this->never())
            ->method('findBySessionId');

        $this->watchSessionRepository
            ->expects($this->never())
            ->method('insert');

        $this->watchSessionRepository
            ->expects($this->never())
            ->method('update');

        $result = $this->service->ingest($event);

        $this->assertSame([
            'status' => 'duplicate_ignored',
            'sessionId' => 'abc-123',
            'eventId' => 'evt-789',
        ], $result);
    }

    public function testItUpdatesAnExistingSession(): void
    {
        $event = $this->validEvent([
            'eventType' => 'heartbeat',
            'eventId' => 'evt-790',
            'eventTimestamp' => '2026-02-10T19:33:15.000Z',
            'receivedAt' => '2026-02-10T19:33:16.000Z',
            'payload' => [
                'eventId' => 'event-2026-wrestling-finals',
                'position' => 1860.0,
                'quality' => '720p',
            ],
        ]);

        $existingSession = [
            'session_id' => 'abc-123',
            'user_id' => 'user-456',
            'event_id' => 'event-2026-wrestling-finals',
            'current_state' => 'active',
            'started_at' => '2026-02-10T19:32:15.000Z',
            'last_event_at' => '2026-02-10T19:32:45.000Z',
            'last_received_at' => '2026-02-10T19:32:46.000Z',
            'last_position' => 1832.5,
            'last_quality' => '1080p',
            'event_count' => 1,
            'total_watch_seconds' => 30,
            'created_at' => '2026-02-10T19:32:16.000Z',
            'updated_at' => '2026-02-10T19:32:46.000Z',
        ];

        $this->utcClock
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-02-10T19:33:16.000Z'));

        $this->watchSessionEventRepository
            ->expects($this->once())
            ->method('insertIfNotExists')
            ->willReturn(true);

        $this->watchSessionRepository
            ->expects($this->once())
            ->method('findBySessionId')
            ->with('abc-123')
            ->willReturn($existingSession);

        $this->watchSessionRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                'abc-123',
                $this->callback(function (array $snapshot): bool {
                    $this->assertSame('abc-123', $snapshot['session_id']);
                    $this->assertSame('user-456', $snapshot['user_id']);
                    $this->assertSame('event-2026-wrestling-finals', $snapshot['event_id']);
                    $this->assertSame('active', $snapshot['current_state']);
                    $this->assertSame('2026-02-10T19:32:15.000Z', $snapshot['started_at']);
                    $this->assertSame('2026-02-10T19:33:15.000Z', $snapshot['last_event_at']);
                    $this->assertSame('2026-02-10T19:33:16.000Z', $snapshot['last_received_at']);
                    $this->assertSame(1860.0, $snapshot['last_position']);
                    $this->assertSame('720p', $snapshot['last_quality']);
                    $this->assertSame(2, $snapshot['event_count']);
                    $this->assertSame(60, $snapshot['total_watch_seconds']);
                    $this->assertSame('2026-02-10T19:33:16.000Z', $snapshot['updated_at']);
                    $this->assertSame('2026-02-10T19:32:16.000Z', $snapshot['created_at']);

                    return true;
                })
            );

        $result = $this->service->ingest($event);

        $this->assertSame([
            'status' => 'accepted',
            'sessionId' => 'abc-123',
            'eventId' => 'evt-790',
        ], $result);
    }

    public function testItRejectsMissingRequiredFields(): void
    {
        $event = $this->validEvent();
        unset($event['userId']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required field: userId');

        $this->watchSessionEventRepository
            ->expects($this->never())
            ->method('insertIfNotExists');

        $this->service->ingest($event);
    }

    public function testItRejectsUnsupportedEventType(): void
    {
        $event = $this->validEvent([
            'eventType' => 'explode',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported event type: explode');

        $this->watchSessionEventRepository
            ->expects($this->never())
            ->method('insertIfNotExists');

        $this->service->ingest($event);
    }

    public function testItKeepsCurrentStateWhenAnOlderEventArrives(): void
    {
        $event = $this->validEvent([
            'eventType' => 'pause',
            'eventId' => 'evt-older',
            'eventTimestamp' => '2026-02-10T19:32:20.000Z',
            'receivedAt' => '2026-02-10T19:34:00.000Z',
        ]);

        $existingSession = [
            'session_id' => 'abc-123',
            'user_id' => 'user-456',
            'event_id' => 'event-2026-wrestling-finals',
            'current_state' => 'active',
            'started_at' => '2026-02-10T19:32:15.000Z',
            'last_event_at' => '2026-02-10T19:33:15.000Z',
            'last_received_at' => '2026-02-10T19:33:16.000Z',
            'last_position' => 1860.0,
            'last_quality' => '720p',
            'event_count' => 2,
            'total_watch_seconds' => 60,
            'created_at' => '2026-02-10T19:32:16.000Z',
            'updated_at' => '2026-02-10T19:33:16.000Z',
        ];

        $this->utcClock
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-02-10T19:34:01.000Z'));

        $this->watchSessionEventRepository
            ->method('insertIfNotExists')
            ->willReturn(true);

        $this->watchSessionRepository
            ->method('findBySessionId')
            ->willReturn($existingSession);

        $this->watchSessionRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                'abc-123',
                $this->callback(function (array $snapshot): bool {
                    $this->assertSame('active', $snapshot['current_state']);
                    $this->assertSame('2026-02-10T19:33:15.000Z', $snapshot['last_event_at']);
                    $this->assertSame('2026-02-10T19:33:16.000Z', $snapshot['last_received_at']);
                    $this->assertSame(1860.0, $snapshot['last_position']);
                    $this->assertSame('720p', $snapshot['last_quality']);
                    $this->assertSame(3, $snapshot['event_count']);
                    $this->assertSame(60, $snapshot['total_watch_seconds']);

                    return true;
                })
            );

        $this->service->ingest($event);
    }

    public function testHeartbeatDoesNotReviveAnEndedSession(): void
    {
        $event = $this->validEvent([
            'eventType' => 'heartbeat',
            'eventId' => 'evt-heartbeat-after-end',
            'eventTimestamp' => '2026-02-10T19:40:00.000Z',
            'receivedAt' => '2026-02-10T19:40:01.000Z',
        ]);

        $existingSession = [
            'session_id' => 'abc-123',
            'user_id' => 'user-456',
            'event_id' => 'event-2026-wrestling-finals',
            'current_state' => 'ended',
            'started_at' => '2026-02-10T19:32:15.000Z',
            'last_event_at' => '2026-02-10T19:39:00.000Z',
            'last_received_at' => '2026-02-10T19:39:01.000Z',
            'last_position' => 2000.0,
            'last_quality' => '1080p',
            'event_count' => 5,
            'total_watch_seconds' => 405,
            'created_at' => '2026-02-10T19:32:16.000Z',
            'updated_at' => '2026-02-10T19:39:01.000Z',
        ];

        $this->utcClock
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-02-10T19:40:02.000Z'));

        $this->watchSessionEventRepository
            ->method('insertIfNotExists')
            ->willReturn(true);

        $this->watchSessionRepository
            ->method('findBySessionId')
            ->willReturn($existingSession);

        $this->watchSessionRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                'abc-123',
                $this->callback(function (array $snapshot): bool {
                    $this->assertSame('ended', $snapshot['current_state']);
                    $this->assertSame('2026-02-10T19:40:00.000Z', $snapshot['last_event_at']);
                    $this->assertSame(6, $snapshot['event_count']);

                    return true;
                })
            );

        $this->service->ingest($event);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validEvent(array $overrides = []): array
    {
        $base = [
            'sessionId' => 'abc-123',
            'userId' => 'user-456',
            'eventType' => 'start',
            'eventId' => 'evt-789',
            'eventTimestamp' => '2026-02-10T19:32:15.000Z',
            'receivedAt' => '2026-02-10T19:32:15.000Z',
            'payload' => [
                'eventId' => 'event-2026-wrestling-finals',
                'position' => 1832.5,
                'quality' => '1080p',
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }
}
