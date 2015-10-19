<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Dns;

use Icicle\Dns;
use Icicle\Dns\Connector\ConnectorInterface;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Resolver\ResolverInterface;
use Icicle\Socket\SocketInterface;
use LibDNS\Messages\Message;

class FunctionsTest extends TestCase
{
    public function testGetExecutor()
    {
        $this->assertInstanceOf(ExecutorInterface::class, Dns\executor());
    }

    /**
     * @depends testGetExecutor
     */
    public function testSetExecutor()
    {
        $executor = $this->getMock(ExecutorInterface::class);
        Dns\executor($executor);

        $executor->expects($this->once())
            ->method('execute');

        Dns\executor()->execute('icicle.io', 'A');
    }

    /**
     * @depends testSetExecutor
     */
    public function testExecute()
    {
        $executor = Dns\executor();

        $domain = 'icicle.io';
        $type = 'A';
        $options = ['timeout' => 1];

        $executor->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($domain), $this->identicalTo($type), $this->identicalTo($options))
            ->will($this->returnCallback(function () {
                return yield $this->getMockBuilder(Message::class)
                    ->disableOriginalConstructor()
                    ->getMock();
            }));

        $this->assertInstanceOf(\Generator::class, Dns\execute($domain, $type, $options));
    }

    public function testGetResolver()
    {
        $this->assertInstanceOf(ResolverInterface::class, Dns\resolver());
    }

    /**
     * @depends testGetResolver
     */
    public function testSetResolver()
    {
        $resolver = $this->getMock(ResolverInterface::class);
        Dns\resolver($resolver);

        $resolver->expects($this->once())
            ->method('resolve');

        Dns\resolver()->resolve('icicle.io');
    }

    /**
     * @depends testSetResolver
     */
    public function testResolve()
    {
        $resolver = Dns\resolver();

        $domain = 'icicle.io';
        $options = ['timeout' => 1];

        $resolver->expects($this->once())
            ->method('resolve')
            ->with($this->identicalTo($domain), $this->identicalTo($options))
            ->will($this->returnCallback(function () {
                return yield [];
            }));

        $this->assertInstanceOf(\Generator::class, Dns\resolve($domain, $options));
    }

    public function testGetConnector()
    {
        $this->assertInstanceOf(ConnectorInterface::class, Dns\connector());
    }

    /**
     * @depends testGetConnector
     */
    public function testSetConnector()
    {
        $connector = $this->getMock(ConnectorInterface::class);
        Dns\connector($connector);

        $connector->expects($this->once())
            ->method('connect');

        Dns\connector()->connect('icicle.io', 80);
    }

    /**
     * @depends testSetConnector
     */
    public function testConnect()
    {
        $connector = Dns\connector();

        $domain = 'icicle.io';
        $port = 80;
        $options = ['timeout' => 1];

        $connector->expects($this->once())
            ->method('connect')
            ->with($this->identicalTo($domain), $this->identicalTo($port), $this->identicalTo($options))
            ->will($this->returnCallback(function () {
                return yield $this->getMock(SocketInterface::class);
            }));

        $this->assertInstanceOf(\Generator::class, Dns\connect($domain, $port, $options));
    }
}
