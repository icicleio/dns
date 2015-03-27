<?php
namespace Icicle\Dns\Executor;

use Icicle\Dns\Query\QueryInterface;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use LibDNS\Records\RecordCollection;
use SplPriorityQueue;

class CachingExecutor implements ExecutorInterface
{
    const MAX_TTL = 65536;
    const INTERVAL = 1;
    
    private $cache = [];
    
    private $executor;
    
    private $callback;
    
    public function __construct(ExecutorInterface $executor)
    {
        $this->executor = $executor;
        $this->callback = $this->createCallback();
    }
    
    public function execute(QueryInterface $query, $timeout = self::DEFAULT_TIMEOUT, $retries = self::DEFAULT_RETRIES)
    {
        $name = $query->getDomain()->getValue();
        $type = $query->getType();
        
        if (isset($this->cache[$name][$type])) {
            return Promise::resolve($this->cache[$name][$type]);
        }
        
        return $this->executor->execute($query, $timeout, $retries)
            ->tap(function (RecordCollection $answers) use ($name, $type) {
                $this->cache[$name][$type] = $answers;
                
                $ttl = self::MAX_TTL;
                
                foreach ($answers as $record) {
                    /** @var \LibDNS\Records\Resource $record */
                    if (($time = $record->getTTL()) < $ttl) {
                        $ttl = $time;
                    }
                }

                $timer = Loop::timer($ttl, $this->callback, $name, $type);
                $timer->unreference();
            });
    }

    protected function createCallback()
    {
        return function ($name, $type) {
            unset($this->cache[$name][$type]);
        };
    }
}
