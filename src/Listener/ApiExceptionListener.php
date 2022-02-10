<?php

namespace Zan\DoctrineRestBundle\Listener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Zan\DoctrineRestBundle\Exception\ApiException;

/**
 * Replace the default serialization of ApiExceptions with one that includes a string-based error code
 */
class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof ApiException) return;


        $response = new JsonResponse(
            [
                'success' => false,
                'errorMessage' => $exception->getMessage(),
                'detail' => $exception->getMessage(),       // compatibility with default Symfony serializer
                'errorCode' => $exception->getClientErrorCode(),
            ],
            500
        );

        $event->setResponse($response);
    }
}