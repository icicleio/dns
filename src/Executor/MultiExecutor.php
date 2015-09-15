<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

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
    public function execute($name, $type, array $options = [])
    {
        if (empty($this->executors)) {
            throw new NoExecutorsError('No executors defined.');
        }

        $executors = $this->executors;
        $count = count($executors);

        for ($i = 0; $i < $count; ++$i) {
            try {
                yield $executors[$i]->execute($name, $type, $options);
                return;
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