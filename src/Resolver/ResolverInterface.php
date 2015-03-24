<?php
namespace Icicle\Dns\Resolver;

use Icicle\Dns\Executor\ExecutorInterface;

interface ResolverInterface
{
    /**
     * @param   string $domain Domain name to resolve.
     * @param   int|float $timeout Time until a request fails
     * @param   int $retries Number of times to retry the request until failing.
     *
     * @return  Icicle\Promise\PromiseInterface
     *
     * @resolve string Resolved IP address.
     *
     * @reject  Icicle\Dns\Execption\FailureException If the server returns a non-zero response code.
     * @reject  Icicle\Dns\Execption\NotFoundException If the domain cannot be resolved.
     */
    public function resolve(
        $domain,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        $retries = ExecutorInterface::DEFAULT_RETRIES
    );
}
