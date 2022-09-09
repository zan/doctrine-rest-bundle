<?php

namespace Zan\DoctrineRestBundle\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponse extends JsonResponse
{
    public function __construct($data, $metadata = null, $total = null)
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        if ($total !== null) {
            $payload['total'] = $total;
        }

        parent::__construct($payload);
    }
}