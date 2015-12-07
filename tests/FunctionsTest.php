<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Dns;

use Icicle\Dns;
use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Socket\Socket;
use LibDNS\Messages\Message;

class FunctionsTest extends TestCase
{
    public function testGetExecutor()
    {
        $this->assertInstanceOf(Executor::class, Dns\executor());
    }

    /**
     * @depends testGetExecutor
     */
    public function testSetExecutor()
    {
        $executor = $this->getMock(Executor::class);
        Dns\executor($executor);

        $this->assertSame($executor, Dns\executor());
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
                yield $this->getMockBuilder(Message::class)
                    ->disableOriginalConstructor()
                    ->getMock();
            }));

        $this->assertInstanceOf(\Generator::class, Dns\execute($domain, $type, $options));
    }

    public function testGetResolver()
    {
        $this->assertInstanceOf(Resolver::class, Dns\resolver());
    }

    /**
     * @depends testGetResolver
     */
    public function testSetResolver()
    {
        $resolver = $this->getMock(Resolver::class);
        Dns\resolver($resolver);

        $this->assertSame($resolver, Dns\resolver());
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
                yield [];
            }));

        $this->assertInstanceOf(\Generator::class, Dns\resolve($domain, $options));
    }

    public function testGetConnector()
    {
        $this->assertInstanceOf(Connector::class, Dns\connector());
    }

    /**
     * @depends testGetConnector
     */
    public function testSetConnector()
    {
        $connector = $this->getMock(Connector::class);
        Dns\connector($connector);

        $this->assertSame($connector, Dns\connector());
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
                yield $this->getMock(Socket::class);
            }));

        $this->assertInstanceOf(\Generator::class, Dns\connect($domain, $port, $options));
    }
}
