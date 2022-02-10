<?php

namespace Zan\DoctrineRestBundle\Exception;

use JetBrains\PhpStorm\Internal\LanguageLevelTypeAware;

class ApiException extends \ErrorException
{
    /*
     * String error code sent to the client
     */
    private string $clientErrorCode;

    public function __construct(string $message = '', string $clientErrorCode = 'Zan.Drest.ApiException')
    {
        parent::__construct($message);

        $this->clientErrorCode = $clientErrorCode;
    }

    public function getClientErrorCode(): string
    {
        return $this->clientErrorCode;
    }
}