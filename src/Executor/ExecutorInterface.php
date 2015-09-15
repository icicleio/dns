<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns\Executor;

interface ExecutorInterface
{
    const DEFAULT_TIMEOUT = 2;
    const DEFAULT_RETRIES = 5;
    
    /**
     * @coroutine
     *
     * @param   string $name Domain name.
     * @param   string|int $type Query type (e.g., 'A', 'MX', 'AAAA', 'NS')
     * @param   mixed[] $options
     *
     * @return  \Generator
     *
     * @resolve \LibDNS\Messages\Message Response message.
     *
     * @throws \Icicle\Dns\Exception\FailureException If sending the request or parsing the response fails.
     * @throws \Icicle\Dns\Exception\MessageException If the server returns a non-zero response code or no response
     *     is received from the server.
     */
    public function execute(string $name, $type, array $options = []): \Generator;
}
