<?php
namespace Icicle\Dns\Executor;

use Icicle\Dns\Query\QueryInterface;

interface ExecutorInterface
{
    const DEFAULT_TIMEOUT = 10;
    
    /**
     * @param   Icicle\Dns\Query\QueryInterface
     * @param   float|int $timeout
     */
    public function execute(QueryInterface $query, $timeout = self::DEFAULT_TIMEOUT);
}
