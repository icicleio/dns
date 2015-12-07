<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns\Connector;

use Icicle\Socket\Connector\Connector as SocketConnector;

interface Connector extends SocketConnector
{
    /**
     * @coroutine
     *
     * @param string $domain Domain name.
     * @param int $port Port number.
     * @param mixed[] $options
     *
     * @return \Generator
     *
     * @resolve \Icicle\Socket\Socket
     *
     * @throws \Icicle\Socket\Exception\FailureException If connecting fails.
     *
     * @see \Icicle\Socket\Connector\Connector::connect() $options are the same as this method.
     */
    public function connect($domain, $port = null, array $options = []);
}
