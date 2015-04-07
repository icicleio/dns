<?php
namespace Icicle\Dns\Exception;

use LibDNS\Messages\Message;

class ResponseIdException extends ResponseException
{
    public function __construct(Message $response)
    {
        parent::__construct("Response ID did not match request ID", $response);
    }
}
