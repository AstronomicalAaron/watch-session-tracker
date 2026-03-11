<?php

declare(strict_types=1);


namespace App\Module\ViewerEventIngestion\RouteHandler;

use App\Module\ViewerEventIngestion\Service\ViewerEventIngestionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ViewerEventIngestionRouteHandler
{
    public function __construct(
        private readonly ViewerEventIngestionService $viewerEventIngestionService,
    ) {
    }

    /**
     * API controllers have to be callable because they are used as action in the routing configuration
     *
     * @param Request $request - the request object
     * @return JsonResponse - the response object
     */
    #[Route('/api/viewer-events', name: 'api_viewer_events_ingest', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            // We get the json contents from the request body and transform it into an associative array that should
            // represent the WatchSessionEvent entity
            /** @var array<string, mixed> $payload */
            $payload = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            // TODO - result would be a WatchSessionEvent Doctrine Entity
            $result = $this->viewerEventIngestionService->ingest($payload);

            $statusCode = $result['status'] === 'accepted'
                ? Response::HTTP_ACCEPTED
                : Response::HTTP_OK;

            return new JsonResponse($result, $statusCode);
        } catch (\JsonException) {
            return new JsonResponse([
                'error' => 'Invalid JSON payload',
            ], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable) {
            return new JsonResponse([
                'error' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
