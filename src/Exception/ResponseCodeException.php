<?php
namespace Icicle\Dns\Exception;

use LibDNS\Messages\Message;

class ResponseCodeException extends ResponseException
{
    public function __construct(Message $response)
    {
        parent::__construct("Response with error code {$response->getResponseCode()}", $response);
    }
}
