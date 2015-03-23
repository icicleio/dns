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
    
    private $queue;
    
    private $executor;
    
    private $timer;
    
    public function __construct(ExecutorInterface $executor)
    {
        $this->executor = $executor;
        $this->queue = new SplPriorityQueue();
        
        $this->timer = Loop::periodic(self::INTERVAL, function () {
            $time = time();
            
            while (!$this->queue->isEmpty()) {
                $entry = $this->queue->top();
                if ($time < $entry['time']) {
                    return;
                }
                unset($this->cache[$entry['name']][$entry['type']]);
                $this->queue->extract();
            }
        });
        $this->timer->unreference();
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
                    if (($time = $record->getTTL()) < $ttl) {
                        $ttl = $time;
                    }
                }
                
                $time = time() + $ttl;
                
                $this->queue->insert(['name' => $name, 'type' => $type, 'time' => $time], -$time);
            });
    }
}
