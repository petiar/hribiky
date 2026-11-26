<?php

namespace App\EventListener;

use App\Service\UploadLimits;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keď request presiahne post_max_size, PHP zahodí CELÉ telo (polia aj súbory) a controller
 * by videl prázdny formulár — web hlásil „Chyba pri odoslaní formulára“ bez dôvodu
 * a API „Missing required field: title“. Tu to zachytíme a vrátime zrozumiteľnú chybu 413.
 *
 * Priorita 200: po kontrole zablokovaných IP (255), pred kontrolou API kľúča (10).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 200)]
class RequestTooLargeListener
{
    public function __construct(
        private readonly UploadLimits $uploadLimits,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $limit = $this->uploadLimits->maxPostBytes();
        $contentLength = (int) $request->server->get('CONTENT_LENGTH', 0);

        if (!$request->isMethod('POST') || $limit === 0 || $contentLength <= $limit) {
            return;
        }

        $limitText = UploadLimits::formatMb($limit);

        if (str_starts_with($request->getPathInfo(), '/api/')) {
            $event->setResponse(new JsonResponse([
                'status' => 'error',
                'code' => 'request_too_large',
                'message' => sprintf('Request is too large (max %s in total). Send smaller or fewer photos.', $limitText),
                'id' => null,
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE));

            return;
        }

        $message = sprintf('Fotky sú spolu príliš veľké (najviac %s). Pošli menej fotiek alebo menšie.', $limitText);
        $event->setResponse(new JsonResponse([
            'success' => false,
            'errors' => [$message],
            'error' => $message,
        ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE));
    }
}
