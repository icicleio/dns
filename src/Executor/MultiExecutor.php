<?php
namespace Icicle\Dns\Executor;

use Exception;
use Icicle\Dns\Executor\Exception\LogicException;
use Icicle\Dns\Query\QueryInterface;
use Icicle\Promise\Promise;
use SplDoublyLinkedList;

class MultiExecutor implements ExecutorInterface
{
    /**
     * @var SplDoublyLinkedList
     */
    private $executors;
    
    /**
     */
    public function __construct()
    {
        $this->executors = new SplDoublyLinkedList();
    }
    
    /**
     * @param   Icicle\Dns\ExecutorInterface
     */
    public function add(ExecutorInterface $executor)
    {
        $this->executors->push($executor);
    }
    
    /**
     * @inheritdoc
     */
    public function execute(QueryInterface $query, $timeout = self::DEFAULT_TIMEOUT, $retries = self::DEFAULT_RETRIES)
    {
        if ($this->executors->isEmpty()) {
            return Promise::reject(new LogicException('No executors defined.'));
        }
        
        $retries = (int) $retries;
        if (0 > $retries) {
            $retries = 0;
        }
        
        $executors = clone $this->executors;
        $count = count($executors);
        
        return Promise::retry(
            function () use ($executors, $query, $timeout) {
                $executor = $executors->bottom();
                return $executor->execute($query, $timeout, 0);
            },
            function (Exception $exception) use ($executors, $count, $retries) {
                static $attempt = 0;
                
                // Shift executor to end of list for this request.
                $executors->push($executors->shift());
                
                // Shift executor to end of list for future requests.
                $this->executors->push($this->executors->shift());
                
                if (floor(++$attempt / $count) > $retries) {
                    return true;
                }
                
                return false;
            }
        );
    }
}