<?php
namespace Icicle\Dns\Resolver;

use Icicle\Dns\Executor\ExecutorInterface;

interface ResolverInterface
{
    /**
     * @param   string $domain Domain name to resolve.
     * @param   int|float $timeout Maximum time until request fails.
     *
     * @return  Icicle\Promise\PromiseInterface
     *
     * @resolve string Resolved IP address.
     *
     * @reject  Icicle\Dns\Query\Execption\FailureException If the server returns a non-zero response code.
     * @reject  Icicle\Dns\Query\Execption\NotFoundException If the domain cannot be resolved.
     */
    public function resolve($domain, $timeout = ExecutorInterface::DEFAULT_TIMEOUT);
}
