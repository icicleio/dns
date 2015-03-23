<?php
namespace Icicle\Dns\Executor;

use Icicle\Dns\Query\QueryInterface;

interface ExecutorInterface
{
    const DEFAULT_TIMEOUT = 2;
    const DEFAULT_RETRIES = 5;
    
    /**
     * @param   Icicle\Dns\Query\QueryInterface
     * @param   float|int $timeout
     * @param   int $retries Number of times to retry the request until failing.
     *
     * @resolve LibDNS\Records\RecordCollection
     *
     * @reject  FailureException If the execution fails.
     * @reject  NotFoundException If a record is not found for the given domain.
     */
    public function execute(
        QueryInterface $query,
        $timeout = self::DEFAULT_TIMEOUT,
        $retries = self::DEFAULT_RETRIES
    );
}
