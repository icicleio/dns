<?php
namespace Icicle\Tests\Dns\Connector;

use Icicle\Dns\Connector\Connector;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
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
        return Mockery::mock('Icicle\Dns\Resolver\ResolverInterface');
    }

    protected function createClientConnector()
    {
        return Mockery::mock('Icicle\Socket\Client\ConnectorInterface');
    }

    public function testConnect()
    {
        $domain = 'example.com';
        $port = 443;
        $timeout = 0.1;
        $retries = 2;
        $ips = ['127.0.0.1'];
        $options = ['timeout' => 0.1, 'cn' => '*.example.com'];

        $this->resolver->shouldReceive('resolve')
            ->with(Mockery::mustBe($domain), Mockery::mustBe($timeout), Mockery::mustBe($retries))
            ->andReturn($ips);

        $this->clientConnector->shouldReceive('connect')
            ->with(Mockery::mustBe($ips[0]), Mockery::mustBe($port), Mockery::type('array'))
            ->andReturn(Promise\resolve(Mockery::mock('Icicle\Socket\Client\ClientInterface')));

        $promise = $this->connector->connect($domain, $port, $options, $timeout, $retries);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

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
            ->with(Mockery::mustBe($domain), Mockery::mustBe($timeout), Mockery::mustBe($retries))
            ->andReturn($ips);

        $this->clientConnector->shouldReceive('connect')
            ->with(Mockery::mustBe($ips[0]), Mockery::mustBe($port), Mockery::type('array'))
            ->andReturn(Promise\reject(new TimeoutException()));

        $promise = $this->connector->connect($domain, $port, $options, $timeout, $retries);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));

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
            ->with(Mockery::mustBe($domain), Mockery::any(), Mockery::type('integer'))
            ->andReturn($ips);

        $this->clientConnector->shouldReceive('connect')
            ->times(2)
            ->andReturnUsing(function () {
                static $initial = true;
                if ($initial) {
                    $initial = false;
                    return Promise\reject(new FailureException());
                }
                return Promise\resolve(Mockery::mock('Icicle\Socket\Client\ClientInterface'));
            });

        $promise = $this->connector->connect($domain, $port);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

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
            ->with(Mockery::mustBe($domain), Mockery::any(), Mockery::type('integer'))
            ->andReturn($ips);

        $this->clientConnector->shouldReceive('connect')
            ->with(identicalTo($ips[0]), identicalTo($port), identicalTo(['name' => $domain]));

        $promise = $this->connector->connect($domain, $port);

        $promise->done($this->createCallback(1), $this->createCallback(0));

        Loop\run();
    }

    /**
     * @return  array
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
            ->andReturn(Promise\resolve($this->getMock('Icicle\Socket\Client\ClientInterface')));

        $promise = $this->connector->connect($ip, $port);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();
    }

    /**
     * @return  array
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
            ->andReturn(Promise\resolve($this->getMock('Icicle\Socket\Client\ClientInterface')));

        $promise = $this->connector->connect($ip, $port);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();
    }
}
