<?php

namespace Zan\DoctrineRestBundle\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiErrorResponse extends JsonResponse
{
    public function __construct(string $code, string $message, int $httpStatus = 500)
    {
        $data = [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];

        parent::__construct($data, $httpStatus, [], false);
    }
}