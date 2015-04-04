<?php
namespace Icicle\Dns\Exception;

use LibDNS\Messages\Message;

class ResponseException extends MessageException
{
    private $response;

    public function __construct(Message $response)
    {
        parent::__construct("Response with error code {$response->getResponseCode()}");

        $this->response = $response;
    }

    /**
     * @return  \LibDNS\Messages\Message
     */
    public function getResponse()
    {
        return $this->response;
    }
}
