<?php
namespace Icicle\Tests\Dns\Connector;

use Icicle\Dns\Connector\Connector;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Tests\Dns\TestCase;

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
        return $this->getMock('Icicle\Dns\Resolver\ResolverInterface');
    }

    protected function createClientConnector()
    {
        return $this->getMock('Icicle\Socket\Client\ConnectorInterface');
    }

    public function testConnect()
    {
        $domain = 'example.com';
        $port = 443;
        $timeout = 0.1;
        $retries = 2;
        $ips = ['127.0.0.1'];
        $options = ['timeout' => 0.1, 'name' => '*.example.com'];

        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($domain, $timeout, $retries)
            ->will($this->returnValue($ips));

        $this->clientConnector->expects($this->once())
            ->method('connect')
            ->with($ips[0], $port, $options)
            ->will($this->returnValue(
                Promise::resolve($this->getMock('Icicle\Socket\Client\ClientInterface'))
            ));

        $promise = $this->connector->connect($domain, $port, $options, $timeout, $retries);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

        Loop::run();
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

        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($domain, $timeout, $retries)
            ->will($this->returnValue($ips));

        $this->clientConnector->expects($this->once())
            ->method('connect')
            ->with($ips[0], $port, $options)
            ->will($this->returnValue(
                Promise::reject(new TimeoutException())
            ));

        $promise = $this->connector->connect($domain, $port, $options, $timeout, $retries);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }

    /**
     * @depends testConnectFailure
     */
    public function testConnectWithMultipleIPs()
    {
        $domain = 'example.com';
        $port = 443;
        $ips = ['127.0.0.1', '127.0.0.2', '127.0.0.3'];

        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($domain)
            ->will($this->returnValue($ips));

        $this->clientConnector->expects($this->exactly(2))
            ->method('connect')
            ->will($this->returnCallback(function () {
                static $initial = true;
                if ($initial) {
                    $initial = false;
                    return Promise::reject(new FailureException());
                }
                return Promise::resolve($this->getMock('Icicle\Socket\Client\ClientInterface'));
            }));

        $promise = $this->connector->connect($domain, $port);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

        Loop::run();
    }

    /**
     * @depends testConnect
     */
    public function testDomainUsedAsDefaultName()
    {
        $domain = 'example.com';
        $port = 80;
        $ips = ['127.0.0.1'];

        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($domain)
            ->will($this->returnValue($ips));

        $this->clientConnector->expects($this->once())
            ->method('connect')
            ->with($ips[0], $port, ['name' => $domain]);

        $promise = $this->connector->connect($domain, $port);

        $promise->done($this->createCallback(1), $this->createCallback(0));

        Loop::run();
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

        $this->resolver->expects($this->never())
            ->method('resolve');

        $this->clientConnector->expects($this->once())
            ->method('connect')
            ->with($ip, $port)
            ->will($this->returnValue(
                Promise::resolve($this->getMock('Icicle\Socket\Client\ClientInterface'))
            ));

        $promise = $this->connector->connect($ip, $port);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

        Loop::run();
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

        $this->resolver->expects($this->never())
            ->method('resolve');

        $this->clientConnector->expects($this->once())
            ->method('connect')
            ->with($ip, $port)
            ->will($this->returnValue(
                Promise::resolve($this->getMock('Icicle\Socket\Client\ClientInterface'))
            ));

        $promise = $this->connector->connect($ip, $port);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

        Loop::run();
    }
}
