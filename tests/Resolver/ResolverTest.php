<?php
namespace Icicle\Tests\Dns\Resolver;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\NotFoundException;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop;
use Icicle\Tests\Dns\TestCase;
use LibDNS\Records\ResourceQTypes;
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
            ->with(Mockery::mustBe($domain), Mockery::mustBe(ResourceQTypes::A), Mockery::type('array'))
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

    public function testLocalhost()
    {
        $this->executor->shouldReceive('execute')
            ->never();

        $coroutine = new Coroutine($this->resolver->resolve('localhost'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->equalTo(['127.0.0.1']));

        $coroutine->done($callback);

        Loop\run();
    }
}
