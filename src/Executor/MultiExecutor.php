<?php
namespace Icicle\Dns\Executor;

use Icicle\Dns\Exception\MessageException;
use Icicle\Dns\Exception\NoExecutorsError;

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
    public function execute(
        string $name,
        $type,
        float $timeout = self::DEFAULT_TIMEOUT,
        int $retries = self::DEFAULT_RETRIES
    ): \Generator {
        $retries = (int) $retries;
        if (0 > $retries) {
            $retries = 0;
        }

        if ($this->executors->isEmpty()) {
            throw new NoExecutorsError('No executors defined.');
        }

        $executors = clone $this->executors;
        $retries = ($retries + 1) * count($executors) - 1;

        $attempt = 0;

        do {
            $executor = $executors->shift();

            try {
                return yield from $executor->execute($name, $type, $timeout, 0);
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