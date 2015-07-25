<?php
namespace Icicle\Dns\Resolver;

use Icicle\Dns\Executor\ExecutorInterface;

interface ResolverInterface
{
    /**
     * @coroutine
     *
     * @param   string $domain Domain name to resolve.
     * @param   int|float $timeout Time until a request fails
     * @param   int $retries Number of times to retry the request until failing.
     *
     * @return  \Generator
     *
     * @resolve string[] List of IP address. May return an empty array if the host cannot be found.
     *
     * @reject \Icicle\Dns\Exception\FailureException If the server returns a non-zero response code.
     */
    public function resolve(
        $domain,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        $retries = ExecutorInterface::DEFAULT_RETRIES
    );
}
