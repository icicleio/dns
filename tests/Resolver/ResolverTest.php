<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Dns\Resolver;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\InvalidArgumentError;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Dns\Resolver\ResolverInterface;
use Icicle\Loop;
use Icicle\Tests\Dns\TestCase;
use Mockery;

class ResolverTest extends TestCase
{
    /**
     * @var \Icicle\Dns\Executor\ExecutorInterface
     */
    protected $executor;

    /**
     * @var \Icicle\Dns\Resolver\Resolver
     */
    protected $resolver;

    public function setUp()
    {
        $this->executor = $this->createExecutor();

        $this->resolver = new Resolver($this->executor);
    }

    /**
     * @return \Icicle\Dns\Executor\ExecutorInterface
     */
    public function createExecutor()
    {
        return Mockery::mock(ExecutorInterface::class);
    }

    /**
     * @dataProvider getARecords
     */
    public function testResolve($domain, $request, $response, array $answers = null, $authority = null)
    {
        $this->executor->shouldReceive('execute')
            ->with(Mockery::mustBe($domain), Mockery::mustBe(1), Mockery::type('array'))
            ->andReturn($this->createMessage($answers, $authority));

        $coroutine = new Coroutine($this->resolver->resolve($domain));

        $result = [];

        foreach ($answers as $answer) {
            if ($answer['type'] === 1) {
                $result[] = $answer['value'];
            }
        }

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->equalTo($result));

        $coroutine->done($callback);

        Loop\run();
    }

    /**
     * @depends testResolve
     */
    public function testNotFound()
    {
        $domain = 'example.com';

        $this->executor->shouldReceive('execute')
            ->andReturn($this->createMessage());

        $coroutine = new Coroutine($this->resolver->resolve($domain));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo([]));

        $coroutine->done($callback);

        Loop\run();
    }

    public function testInvalidMode()
    {
        $this->executor->shouldReceive('execute')
            ->never();

        $coroutine = new Coroutine($this->resolver->resolve('localhost', ['mode' => 0]));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(InvalidArgumentError::class));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testLocalhost()
    {
        $this->executor->shouldReceive('execute')
            ->never();

        $coroutine = new Coroutine($this->resolver->resolve('localhost'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->equalTo(['127.0.0.1']));

        $coroutine->done($callback);

        $coroutine = new Coroutine($this->resolver->resolve('localhost', ['mode' => ResolverInterface::IPv6]));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->equalTo(['::1']));

        $coroutine->done($callback);

        Loop\run();
    }
}
