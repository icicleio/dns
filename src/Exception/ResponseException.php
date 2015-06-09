<?php
namespace Icicle\Dns\Exception;

use LibDNS\Messages\Message;

class ResponseException extends MessageException
{
    private $response;

    public function __construct($message, Message $response)
    {
        parent::__construct($message);

        $this->response = $response;
    }

    /**
     * @return \LibDNS\Messages\Message
     */
    public function getResponse()
    {
        return $this->response;
    }
}
