<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\SessionQuery\Service;

use App\Module\SessionQuery\Service\SessionQueryService;
use App\Repository\WatchSessionEventRepository;
use App\Repository\WatchSessionRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SessionQueryServiceTest extends TestCase
{
    private WatchSessionRepository&MockObject $watchSessionRepository;
    private WatchSessionEventRepository&MockObject $watchSessionEventRepository;
    private SessionQueryService $service;

    protected function setUp(): void
    {
        $this->watchSessionRepository = $this->createMock(WatchSessionRepository::class);
        $this->watchSessionEventRepository = $this->createMock(WatchSessionEventRepository::class);

        $this->service = new SessionQueryService(
            $this->watchSessionRepository,
            $this->watchSessionEventRepository,
        );
    }

    public function testItReturnsSessionDetails(): void
    {
        $sessionId = 'abc-123';

        $session = [
            'session_id' => 'abc-123',
            'user_id' => 'user-456',
            'event_id' => 'event-2026-wrestling-finals',
            'current_state' => 'active',
            'started_at' => '2026-02-10T19:32:15+00:00',
            'last_event_at' => '2026-02-10T19:33:15+00:00',
            'last_received_at' => '2026-02-10T19:33:16+00:00',
            'total_watch_seconds' => 60,
            'event_count' => 2,
            'last_position' => 60.0,
            'last_quality' => '1080p',
        ];

        $events = [
            [
                'event_row_id' => 'evt-1',
                'event_type' => 'start',
                'sdk_event_timestamp' => '2026-02-10T19:32:15+00:00',
                'received_at' => '2026-02-10T19:32:15+00:00',
                'stream_event_id' => 'event-2026-wrestling-finals',
                'position' => 0.0,
                'quality' => '1080p',
            ],
            [
                'event_row_id' => 'evt-2',
                'event_type' => 'heartbeat',
                'sdk_event_timestamp' => '2026-02-10T19:33:15+00:00',
                'received_at' => '2026-02-10T19:33:16+00:00',
                'stream_event_id' => 'event-2026-wrestling-finals',
                'position' => 60.0,
                'quality' => '1080p',
            ],
        ];

        $this->watchSessionRepository
            ->expects($this->once())
            ->method('findBySessionId')
            ->with($sessionId)
            ->willReturn($session);

        $this->watchSessionEventRepository
            ->expects($this->once())
            ->method('findBySessionId')
            ->with($sessionId)
            ->willReturn($events);

        $result = $this->service->getSessionDetails($sessionId);

        $this->assertSame([
            'sessionId' => 'abc-123',
            'userId' => 'user-456',
            'eventId' => 'event-2026-wrestling-finals',
            'currentState' => 'active',
            'startedAt' => '2026-02-10T19:32:15+00:00',
            'lastEventAt' => '2026-02-10T19:33:15+00:00',
            'lastReceivedAt' => '2026-02-10T19:33:16+00:00',
            'durationSoFarSeconds' => 60,
            'eventsReceived' => 2,
            'lastPosition' => 60.0,
            'lastQuality' => '1080p',
            'events' => $events,
        ], $result);
    }

    public function testItThrowsWhenSessionDoesNotExist(): void
    {
        $sessionId = 'does-not-exist';

        $this->watchSessionRepository
            ->expects($this->once())
            ->method('findBySessionId')
            ->with($sessionId)
            ->willReturn(null);

        $this->watchSessionEventRepository
            ->expects($this->never())
            ->method('findBySessionId');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session not found');

        $this->service->getSessionDetails($sessionId);
    }
}
