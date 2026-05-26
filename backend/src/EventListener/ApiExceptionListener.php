<?php

namespace App\EventListener;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Converts security exceptions to clean JSON responses for the API.
 *
 * Symfony's JWT stateless firewall throws AccessDeniedException in two
 * distinct situations that must map to different HTTP codes:
 *
 *   - No valid token / anonymous user  →  401 Unauthorized
 *   - Authenticated but wrong role     →  403 Forbidden
 *
 * We distinguish them by asking Security::isGranted('IS_AUTHENTICATED_FULLY').
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class ApiExceptionListener
{
    public function __construct(private readonly Security $security) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedException) {
            return;
        }

        if (!$this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
            $event->setResponse(new JsonResponse(
                ['message' => 'Authentication required.'],
                Response::HTTP_UNAUTHORIZED
            ));
            return;
        }

        $event->setResponse(new JsonResponse(
            ['message' => 'Access denied. You do not have permission to perform this action.'],
            Response::HTTP_FORBIDDEN
        ));
    }
}
