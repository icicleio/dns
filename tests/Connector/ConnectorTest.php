<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Dns\Connector;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Exception\NoResponseException;
use Icicle\Dns\Resolver\ResolverInterface;
use Icicle\Loop;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Client\ConnectorInterface;
use Icicle\Socket\Exception\FailureException;
use Icicle\Tests\Dns\TestCase;
use Mockery;

class ConnectorTest extends TestCase
{
    /**
     * @var \Icicle\Dns\Resolver\ResolverInterface
     */
    protected $resolver;

    /**
     * @var \Icicle\Socket\Client\ConnectorInterface
     */
    protected $clientConnector;

    /**
     * @var \Icicle\Dns\Connector\Connector;
     */
    protected $connector;

    public function setUp()
    {
        $this->resolver = $this->createResolver();

        $this->clientConnector = $this->createClientConnector();

        $this->connector = new Connector($this->resolver, $this->clientConnector);
    }

    protected function createResolver()
    {
        return Mockery::mock(ResolverInterface::class);
    }

    protected function createClientConnector()
    {
        return Mockery::mock(ConnectorInterface::class);
    }

    public function testConnect()
    {
        $domain = 'example.com';
        $port = 443;
        $ips = ['127.0.0.1'];
        $options = ['timeout' => 0.1, 'cn' => '*.example.com'];

        $generator = function ($value) {
            return yield $value;
        };

        $this->resolver->shouldReceive('resolve')
            ->with(Mockery::mustBe($domain), Mockery::type('array'))
            ->andReturn($generator($ips));

        $this->clientConnector->shouldReceive('connect')
            ->with(Mockery::mustBe($ips[0]), Mockery::mustBe($port), Mockery::type('array'))
            ->andReturn($generator(Mockery::mock(ClientInterface::class)));

        $promise = new Coroutine($this->connector->connect($domain, $port, $options));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(ClientInterface::class));

        $promise->done($callback);

        Loop\run();
    }

    /**
     * @depends testConnect
     */
    public function testConnectFailure()
    {
        $domain = 'example.com';
        $port = 443;
        $timeout = 0.1;
        $retries = 2;
        $ips = ['127.0.0.1'];
        $options = ['timeout' => 0.1, 'name' => '*.example.com'];

        $this->resolver->shouldReceive('resolve')
            ->with(Mockery::mustBe($domain), Mockery::type('array'))
            ->andReturnUsing(function () use ($ips) { return yield $ips; });

        $this->clientConnector->shouldReceive('connect')
            ->with(Mockery::mustBe($ips[0]), Mockery::mustBe($port), Mockery::type('array'))
            ->andReturnUsing(function () {
                throw new TimeoutException('Request timed out.'); yield;
            });

        $promise = new Coroutine($this->connector->connect($domain, $port, $options, $timeout, $retries));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(FailureException::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testResolveEmpty()
    {
        $domain = 'example.com';
        $port = 443;
        $timeout = 0.1;
        $retries = 2;
        $options = ['timeout' => 0.1, 'name' => '*.example.com'];

        $this->resolver->shouldReceive('resolve')
            ->with(Mockery::mustBe($domain), Mockery::type('array'))
            ->andReturnUsing(function () { return yield []; });

        $promise = new Coroutine($this->connector->connect($domain, $port, $options, $timeout, $retries));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(FailureException::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testResolveFailure()
    {
        $domain = 'example.com';
        $port = 443;
        $timeout = 0.1;
        $retries = 2;
        $options = ['timeout' => 0.1, 'name' => '*.example.com'];

        $this->resolver->shouldReceive('resolve')
            ->with(Mockery::mustBe($domain), Mockery::type('array'))
            ->andThrow(NoResponseException::class);

        $promise = new Coroutine($this->connector->connect($domain, $port, $options, $timeout, $retries));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(FailureException::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @depends testConnectFailure
     */
    public function testConnectWithMultipleIPs()
    {
        $domain = 'example.com';
        $port = 443;
        $ips = ['127.0.0.1', '127.0.0.2', '127.0.0.3'];

        $this->resolver->shouldReceive('resolve')
            ->with(Mockery::mustBe($domain), Mockery::type('array'))
            ->andReturnUsing(function () use ($ips) { return yield $ips; });

        $this->clientConnector->shouldReceive('connect')
            ->times(2)
            ->andReturnUsing(function () {
                static $initial = true;
                $generator = function () use (&$initial) {
                    if ($initial) {
                        $initial = false;
                        throw new FailureException();
                    }
                    return yield Mockery::mock(ClientInterface::class);
                };
                return $generator();
            });

        $promise = new Coroutine($this->connector->connect($domain, $port));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(ClientInterface::class));

        $promise->done($callback);

        Loop\run();
    }

    /**
     * @depends testConnect
     */
    public function testDomainUsedAsDefaultName()
    {
        $domain = 'example.com';
        $port = 80;
        $ips = ['127.0.0.1'];

        $this->resolver->shouldReceive('resolve')
            ->with(Mockery::mustBe($domain), Mockery::type('array'))
            ->andReturnUsing(function () use ($ips) { return yield $ips; });

        $this->clientConnector->shouldReceive('connect')
            ->with(identicalTo($ips[0]), identicalTo($port), identicalTo(['name' => $domain]));

        $promise = new Coroutine($this->connector->connect($domain, $port));

        $promise->done($this->createCallback(1));

        Loop\run();
    }

    /**
     * @return array
     */
    public function getIPv4Addresses()
    {
        return [
            ['127.0.0.1'],
            ['216.58.216.110'],
            ['199.38.81.196']
        ];
    }

    /**
     * @dataProvider getIPv4Addresses
     */
    public function testConnectWithIPv4Address($ip)
    {
        $ip = '216.58.216.110';
        $port = 80;

        $this->resolver->shouldReceive('resolve')
            ->never();

        $this->clientConnector->shouldReceive('connect')
            ->with(Mockery::mustBe($ip), Mockery::mustBe($port), Mockery::any())
            ->andReturnUsing(function () {
                return yield Mockery::mock(ClientInterface::class);
            });

        $promise = new Coroutine($this->connector->connect($ip, $port));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(ClientInterface::class));

        $promise->done($callback);

        Loop\run();
    }

    /**
     * @return array
     */
    public function getIPv6Addresses()
    {
        return [
            ['::1'],
            ['[::1]'],
            ['2607:f8b0:4009:806::1001'],
            ['[2001:db8::ff00:42:8329]']
        ];
    }

    /**
     * @dataProvider getIPv6Addresses
     */
    public function testConnectWithIPv6Address($ip)
    {
        $port = 80;

        $this->resolver->shouldReceive('resolve')
            ->never();

        $this->clientConnector->shouldReceive('connect')
            ->with(Mockery::mustBe($ip), Mockery::mustBe($port), Mockery::any())
            ->andReturnUsing(function () {
                return yield Mockery::mock(ClientInterface::class);
            });

        $promise = new Coroutine($this->connector->connect($ip, $port));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(ClientInterface::class));

        $promise->done($callback);

        Loop\run();
    }
}
