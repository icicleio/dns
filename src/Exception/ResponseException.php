<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

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
