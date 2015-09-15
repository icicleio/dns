<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns\Connector;

interface ConnectorInterface extends \Icicle\Socket\Client\ConnectorInterface
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
     * @resolve \Icicle\Socket\Client\ClientInterface
     *
     * @throws \Icicle\Socket\Exception\FailureException If connecting fails.
     *
     * @see \Icicle\Socket\Client\Connector::connect() $options are the same as this method.
     */
    public function connect(string $domain, int $port, array $options = []): \Generator;
}
