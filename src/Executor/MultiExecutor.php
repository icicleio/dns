<?php
namespace Icicle\Dns\Executor;

use Icicle\Dns\Exception\MessageException;
use Icicle\Dns\Exception\NoExecutorsError;

class MultiExecutor implements ExecutorInterface
{
    /**
     * @var ExecutorInterface[]
     */
    private $executors = [];
    
    /**
     * @param \Icicle\Dns\Executor\ExecutorInterface
     */
    public function add(ExecutorInterface $executor)
    {
        $this->executors[] = $executor;
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute(string $name, $type, array $options = []): \Generator
    {
        if (empty($this->executors)) {
            throw new NoExecutorsError('No executors defined.');
        }

        $executors = $this->executors;
        $count = count($executors);

        for ($i = 0; $i < $count; ++$i) {
            try {
                return yield from $executors[$i]->execute($name, $type, $options);
            } catch (MessageException $exception) {
                // If it is still at the head, shift executor in main list to the tail for future requests.
                if ($this->executors[0] === $executors[$i]) {
                    $this->executors[] = array_shift($this->executors);
                }
            }
        }

        throw $exception;
    }
}