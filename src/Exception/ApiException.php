<?php

namespace Zan\DoctrineRestBundle\Exception;

use Zan\DoctrineRestBundle\Api\Error;

class ApiException extends \ErrorException
{
    /*
     * String error code sent to the client
     */
    private string $clientErrorCode;

    public function __construct(string $message = '', string $clientErrorCode = Error::GENERIC_ERROR)
    {
        parent::__construct($message);

        $this->clientErrorCode = $clientErrorCode;
    }

    public function getClientErrorCode(): string
    {
        return $this->clientErrorCode;
    }
}