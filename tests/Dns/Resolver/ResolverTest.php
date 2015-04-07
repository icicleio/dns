<?php
namespace Icicle\Tests\Dns\Resolver;

use Icicle\Dns\Exception\NotFoundException;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop\Loop;
use Icicle\Tests\Dns\TestCase;
use LibDNS\Records\ResourceQTypes;

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
     * @return  \Icicle\Dns\Executor\ExecutorInterface
     */
    public function createExecutor()
    {
        return $this->getMock('Icicle\Dns\Executor\ExecutorInterface');
    }

    /**
     * @dataProvider getARecords
     */
    public function testResolve($domain, $request, $response, array $answers = null, $authority = null)
    {
        $this->executor->expects($this->once())
            ->method('execute')
            ->with($domain, ResourceQTypes::A)
            ->will($this->returnValue($this->createMessage($answers, $authority)));

        $promise = $this->resolver->resolve($domain);

        $result = [];

        foreach ($answers as $answer) {
            if ($answer['type'] === 1) {
                $result[] = $answer['value'];
            }
        }

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->equalTo($result));

        $promise->done($callback, $this->createCallback(0));

        Loop::run();
    }

    /**
     * @depends testResolve
     */
    public function testNotFound()
    {
        $domain = 'example.com';

        $this->executor->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($this->createMessage()));

        $promise = $this->resolver->resolve($domain);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\NotFoundException'))
            ->will($this->returnCallback(function (NotFoundException $exception) use ($domain) {
                $this->assertSame($domain, $exception->getName());
                $this->assertSame(ResourceQTypes::A, $exception->getType());
            }));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }

    public function testLocalhost()
    {
        $this->executor->expects($this->never())
            ->method('execute');

        $promise = $this->resolver->resolve('localhost');

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->equalTo(['127.0.0.1']));

        $promise->done($callback, $this->createCallback(0));

        Loop::run();
    }
}
