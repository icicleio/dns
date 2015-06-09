<?php
namespace Icicle\Dns\Executor;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\LogicException;
use Icicle\Dns\Exception\MessageException;

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
     * @param \Icicle\Dns\Executor\ExecutorInterface
     */
    public function add(ExecutorInterface $executor)
    {
        $this->executors->push($executor);
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute($name, $type, $timeout = self::DEFAULT_TIMEOUT, $retries = self::DEFAULT_RETRIES)
    {
        $retries = (int) $retries;
        if (0 > $retries) {
            $retries = 0;
        }
        
        return new Coroutine($this->run($name, $type, $timeout, $retries));
    }

    /**
     * @coroutine
     *
     * @param string $name Domain name.
     * @param string|int $type Record type (e.g., 'A', 'MX', 'AAAA', 'NS' or integer value of type)
     * @param float|int $timeout Seconds until a request times out.
     * @param int $retries Number of times to attempt the request.
     *
     * @return \Generator
     *
     * @resolve \LibDNS\Messages\Message
     *
     * @reject \Icicle\Dns\Exception\LogicException If no executors are defined.
     * @reject \Icicle\Dns\Exception\FailureException If the server responds with a non-zero response code or does
     *     not respond at all.
     * @reject \Icicle\Dns\Exception\MessageException If there is a problem with the response or no response.
     */
    protected function run($name, $type, $timeout, $retries)
    {
        if ($this->executors->isEmpty()) {
            throw new LogicException('No executors defined.');
        }

        $executors = clone $this->executors;
        $retries = ($retries + 1) * count($executors) - 1;

        $attempt = 0;

        do {
            $executor = $executors->shift();

            try {
                yield $executor->execute($name, $type, $timeout, 0);
                return;
            } catch (MessageException $exception) {
                // Push executor to the end of the list for this request.
                $executors->push($executor);

                // If it is still at the head, shift executor in main list to the tail for future requests.
                if ($this->executors->bottom() === $executor) {
                    $this->executors->push($this->executors->shift());
                }
            }
        } while (++$attempt <= $retries);

        throw $exception;
    }
}