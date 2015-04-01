<?php
namespace Icicle\Dns\Executor;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\FailureException;
use Icicle\Dns\Exception\LogicException;
use Icicle\Dns\Query\QueryInterface;

class MultiExecutor implements ExecutorInterface
{
    /**
     * @var \SplDoublyLinkedList
     */
    private $executors;
    
    /**
     */
    public function __construct()
    {
        $this->executors = new \SplDoublyLinkedList();
    }
    
    /**
     * @param   \Icicle\Dns\Executor\ExecutorInterface
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
        $retries = (int) $retries;
        if (0 > $retries) {
            $retries = 0;
        }
        
        return new Coroutine($this->run($query, $timeout, $retries));
    }

    /**
     * @param   \Icicle\Dns\Query\QueryInterface $query
     * @param   $timeout
     * @param   $retries
     *
     * @return  \Generator
     *
     * @resolve \LibDNS\Records\RecordCollection
     *
     * @reject  \Icicle\Dns\Exception\FailureException If no servers respond to the query.
     * @reject  \Icicle\Dns\Exception\NotFoundException If no record of the given type is found for the domain.
     */
    protected function run(QueryInterface $query, $timeout, $retries)
    {
        if ($this->executors->isEmpty()) {
            throw new LogicException('No executors defined.');
        }

        $executors = clone $this->executors;
        $count = count($executors);

        $retries = ($retries + 1) * $count;

        $attempt = 0;

        do {
            $executor = $executors->shift();

            try {
                yield $executor->execute($query, $timeout, 0);
                return;
            } catch (FailureException $exception) {
                // Push executor to the end of the list for this request.
                $executors->push($executor);

                // If it is still at the head, shift executor in main list to the tail for future requests.
                if ($this->executors->bottom() === $executor) {
                    $this->executors->push($this->executors->shift());
                }
            }
        } while (++$attempt < $retries);

        throw new FailureException('No DNS servers responded to the query.');
    }
}