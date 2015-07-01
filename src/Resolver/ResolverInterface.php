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
     * @resolve string[] List of IP address. Will always contain at least one IP, otherwise the promise is rejected.
     *
     * @reject \Icicle\Dns\Exception\FailureException If the server returns a non-zero response code.
     * @reject \Icicle\Dns\Exception\NotFoundException If the domain cannot be resolved.
     */
    public function resolve(
        $domain,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        $retries = ExecutorInterface::DEFAULT_RETRIES
    );
}
