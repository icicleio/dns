<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Dns\Executor;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\MessageException;
use Icicle\Dns\Exception\NoExecutorsError;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Executor\MultiExecutor;
use Icicle\Loop;
use Icicle\Tests\Dns\TestCase;
use LibDNS\Messages\Message;

class MultiExecutorTest extends TestCase
{
    /**
     * @var \Icicle\Dns\Executor\MultiExecutor
     */
    protected $executor;

    public function setUp()
    {
        $this->executor = new MultiExecutor();
    }

    /**
     * @return \Icicle\Dns\Executor\ExecutorInterface
     */
    public function createExecutor()
    {
        return $this->getMock(ExecutorInterface::class);
    }

    public function testNoExecutors()
    {
        $coroutine = new Coroutine($this->executor->execute('example.com', 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(NoExecutorsError::class));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function execute($type, $domain, $request, $response, array $answers = null, array $authority = null)
    {
        $message = $this->createMessage($answers, $authority);

        $executor = $this->createExecutor();

        $executor->expects($this->once())
            ->method('execute')
            ->with($domain, $type)
            ->will($this->returnCallback(function () use ($message) {
                return yield $message;
            }));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute($domain, $type));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($message));

        $coroutine->done($callback);

        Loop\run();
    }

    /**
     * @dataProvider getARecords
     */
    public function testARecords($domain, $request, $response, array $answers = null, array $authority = null)
    {
        $this->execute('A', $domain, $request, $response, $answers, $authority);
    }

    /**
     * @dataProvider getMxRecords
     */
    public function testMxRecords($domain, $request, $response, array $answers = null, array $authority = null)
    {
        $this->execute('MX', $domain, $request, $response, $answers, $authority);
    }

    /**
     * @dataProvider getNsRecords
     */
    public function testNsRecords($domain, $request, $response, array $answers = null, array $authority = null)
    {
        $this->execute('NS', $domain, $request, $response, $answers, $authority);
    }

    public function testRetriesAfterThrownMessageException()
    {
        $executor = $this->createExecutor();

        $executor->expects($this->once())
            ->method('execute')
            ->will($this->returnCallback(function () {
                throw new MessageException(); yield;
            }));

        $this->executor->add($executor);

        $executor = $this->createExecutor();

        $executor->expects($this->once())
            ->method('execute')
            ->will($this->returnCallback(function () {
                return yield $this->createMessage();
            }));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(Message::class));

        $coroutine->done($callback);

        Loop\run();
    }

    public function testNextRequestUsesLastRespondingExecutor()
    {
        $executor = $this->createExecutor();

        $executor->expects($this->once())
            ->method('execute')
            ->will($this->returnCallback(function () {
                throw new MessageException(); yield;
            }));

        $this->executor->add($executor);

        $executor = $this->createExecutor();

        $executor->expects($this->exactly(2))
            ->method('execute')
            ->will($this->returnCallback(function () {
                return yield $this->createMessage();
            }));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(Message::class));

        $coroutine->done($callback);

        Loop\run(); // Should shift first executor to back of list.

        $coroutine = new Coroutine($this->executor->execute('example.org', 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(Message::class));

        $coroutine->done($callback);

        Loop\run(); // Should call second executor first.
    }

    public function testRetries()
    {
        $exception = new MessageException();
        $timeout = 0.1;
        $retries = 3;

        $executor = $this->createExecutor();

        $generator = function () use ($exception) {
            throw $exception; yield;
        };

        $executor->expects($this->once())
            ->method('execute')
            ->will($this->returnCallback($generator));

        $this->executor->add($executor);

        $executor = $this->createExecutor();

        $executor->expects($this->once())
            ->method('execute')
            ->will($this->returnCallback($generator));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute(
            'example.com',
            'A',
            ['timeout' => $timeout, 'retries' => $retries]
        ));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @depends testRetries
     */
    public function testInvalidRetries()
    {
        $exception = new MessageException();
        $timeout = 0.1;

        $executor = $this->createExecutor();

        $executor->expects($this->once())
            ->method('execute')
            ->will($this->returnCallback(function () use ($exception) {
                throw $exception; yield;
            }));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute(
            'example.com',
            'A',
            ['timeout' => $timeout, 'retries' => -1]
        ));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }
}
