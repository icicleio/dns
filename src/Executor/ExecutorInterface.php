<?php
namespace Icicle\Dns\Executor;

use Icicle\Dns\Query\QueryInterface;

interface ExecutorInterface
{
    const DEFAULT_TIMEOUT = 2;
    const DEFAULT_RETRIES = 5;
    
    /**
     * @param   \Icicle\Dns\Query\QueryInterface $query
     * @param   float|int $timeout Timeout for each individual request.
     * @param   int $retries Number of times to retry the request until failing.
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve \LibDNS\Records\RecordCollection
     *
     * @reject  \Icicle\Dns\Exception\FailureException If the server returns a non-zero response code.
     * @reject  \Icicle\Dns\Exception\NotFoundException If the domain cannot be resolved.
     */
    public function execute(
        QueryInterface $query,
        $timeout = self::DEFAULT_TIMEOUT,
        $retries = self::DEFAULT_RETRIES
    );
}
