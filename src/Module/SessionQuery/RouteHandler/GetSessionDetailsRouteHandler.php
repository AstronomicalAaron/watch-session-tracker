<?php

declare(strict_types=1);

namespace App\Module\SessionQuery\RouteHandler;

use App\Module\SessionQuery\Service\SessionQueryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GetSessionDetailsRouteHandler
{
    public function __construct(
        private readonly SessionQueryService $sessionQueryService,
    ) {
    }

    #[Route('/api/sessions/{sessionId}', name: 'api_session_details', methods: ['GET'])]
    public function __invoke(string $sessionId): JsonResponse
    {
        try {
            $sessionDetails = $this->sessionQueryService->getSessionDetails($sessionId);

            return new JsonResponse($sessionDetails, Response::HTTP_OK);
        } catch (\RuntimeException $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable) {
            return new JsonResponse([
                'error' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
