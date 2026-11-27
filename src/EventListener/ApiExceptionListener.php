<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Chyby v /api/* vracia ako JSON (rovnaký tvar ako ostatné chyby API), nie ako HTML stránku.
 *
 * Priorita -32: až PO zalogovaní výnimky Symfony (ErrorListener::logKernelException, priorita 0) —
 * setResponse() zastaví ďalšie listenery, takže skôr by sa chyba nezalogovala (ani neposlala e-mailom).
 * A pred vykreslením HTML chybovej stránky (onKernelException, -128).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -32)]
final class ApiExceptionListener
{
    private const CODES = [
        Response::HTTP_BAD_REQUEST => 'bad_request',
        Response::HTTP_UNAUTHORIZED => 'unauthorized',
        Response::HTTP_FORBIDDEN => 'forbidden',
        Response::HTTP_NOT_FOUND => 'not_found',
        Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
        Response::HTTP_TOO_MANY_REQUESTS => 'too_many_requests',
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $headers = $exception->getHeaders();
            $message = match ($status) {
                Response::HTTP_NOT_FOUND => 'Not found.',
                Response::HTTP_METHOD_NOT_ALLOWED => 'Method not allowed.',
                default => Response::$statusTexts[$status] ?? 'Error.',
            };
        } else {
            // Neočakávaná chyba — podrobnosti sú v logu (a v e-maile), appke ich neukazujeme.
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $headers = [];
            $message = 'Internal server error.';
        }

        $event->setResponse(new JsonResponse([
            'status' => 'error',
            'code' => self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'http_error'),
            'message' => $message,
        ], $status, $headers));
    }
}
