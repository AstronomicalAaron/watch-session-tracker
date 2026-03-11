<?php

declare(strict_types=1);

namespace App\Module\SessionQuery\RouteHandler;

use App\Psr\SystemClock;
use App\Repository\WatchSessionRepository;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GetActiveSessionCountRouteHandler
{
    // TODO - put this in a Service class, this is business logic
    // Can use int type here since PHP 8.4
    private const int ACTIVE_WINDOW_SECONDS = 45;

    public function __construct(
        // TODO - create a Service class and inject the repository into that and inject the service into the controller
        private readonly WatchSessionRepository $watchSessionRepository,
        private readonly SystemClock $nowUtc,
    ) {
    }

    /**
     *  Responds with the number of active sessions for a given event id
     *
     * @param string $eventId - the id of the event we get from the endpoint
     * @return JsonResponse - the response object with the active session count
     * @throws \DateMalformedStringException - if the current time cannot be parsed
     * @throws Exception - if the query fails
     */
    #[Route('/api/events/{eventId}/active-sessions', name: 'api_active_session_count', methods: ['GET'])]
    public function __invoke(string $eventId): JsonResponse
    {
        $now = $this->nowUtc->now();
        // ->modify() returns a new DateTimeImmutable object instead of mutating the original one by reference
        $threshold = $now->modify(sprintf('-%d seconds', self::ACTIVE_WINDOW_SECONDS));

        $count = $this->watchSessionRepository->countActiveByEventId($eventId, $threshold);

        return new JsonResponse([
            'eventId' => $eventId,
            'activeSessionCount' => $count,
            'asOf' => $now->format(SystemClock::DATE_FORMAT),
            'activeWindowSeconds' => self::ACTIVE_WINDOW_SECONDS,
        ], Response::HTTP_OK);
    }
}
