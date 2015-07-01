<?php
namespace Icicle\Tests\Dns\Executor;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\MessageException;
use Icicle\Dns\Executor\MultiExecutor;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Tests\Dns\TestCase;

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
        return $this->getMock('Icicle\Dns\Executor\ExecutorInterface');
    }

    public function testNoExecutors()
    {
        $coroutine = new Coroutine($this->executor->execute('example.com', 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\LogicException'));

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
            ->will($this->returnValue(Promise\resolve($message)));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute($domain, $type));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($message));

        $coroutine->done($callback, $this->createCallback(0));

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
            ->will($this->returnValue(Promise\reject(new MessageException())));

        $this->executor->add($executor);

        $executor = $this->createExecutor();

        $executor->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(Promise\resolve($this->createMessage())));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('LibDNS\Messages\Message'));

        $coroutine->done($callback, $this->createCallback(0));

        Loop\run();
    }

    public function testNextRequestUsesLastRespondingExecutor()
    {
        $executor = $this->createExecutor();

        $executor->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(Promise\reject(new MessageException())));

        $this->executor->add($executor);

        $executor = $this->createExecutor();

        $executor->expects($this->exactly(2))
            ->method('execute')
            ->will($this->returnValue(Promise\resolve($this->createMessage())));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('LibDNS\Messages\Message'));

        $coroutine->done($callback, $this->createCallback(0));

        Loop\run(); // Should shift first executor to back of list.

        $coroutine = new Coroutine($this->executor->execute('example.org', 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('LibDNS\Messages\Message'));

        $coroutine->done($callback, $this->createCallback(0));

        Loop\run(); // Should call second executor first.
    }

    public function testRetries()
    {
        $exception = new MessageException();
        $timeout = 0.1;
        $retries = 3;

        $executor = $this->createExecutor();

        $executor->expects($this->exactly($retries + 1))
            ->method('execute')
            ->will($this->returnValue(Promise\reject($exception)));

        $this->executor->add($executor);

        $executor = $this->createExecutor();

        $executor->expects($this->exactly($retries + 1))
            ->method('execute')
            ->will($this->returnValue(Promise\reject($exception)));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A', $timeout, $retries));

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
            ->will($this->returnValue(Promise\reject($exception)));

        $this->executor->add($executor);

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A', $timeout, -1));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }
}
