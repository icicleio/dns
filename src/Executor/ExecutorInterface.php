<?php
namespace Icicle\Dns\Executor;

interface ExecutorInterface
{
    const DEFAULT_TIMEOUT = 2;
    const DEFAULT_RETRIES = 5;
    
    /**
     * @param   string $name Domain name.
     * @param   string|int $type Query type (e.g., 'A', 'MX', 'AAAA', 'NS')
     * @param   float|int $timeout Timeout for each individual request.
     * @param   int $retries Number of times to retry the request until failing.
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve \LibDNS\Messages\Message Response message.
     *
     * @reject  \Icicle\Dns\Exception\FailureException If sending the request or parsing the response fails.
     * @reject  \Icicle\Dns\Exception\MessageException If the server returns a non-zero response code or not response
     *          is received from the server.
     */
    public function execute($name, $type, $timeout = self::DEFAULT_TIMEOUT, $retries = self::DEFAULT_RETRIES);
}
