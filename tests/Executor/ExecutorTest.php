<?php
namespace Icicle\Tests\Dns\Executor;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\InvalidTypeException;
use Icicle\Dns\Exception\ResponseIdException;
use Icicle\Dns\Executor\Executor;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Tests\Dns\TestCase;
use LibDNS\Messages\Message;
use Mockery;

class ExecutorTest extends TestCase
{
    const ADDRESS = '127.0.0.1';
    const PORT = 53;
    const ID_LENGTH = 2;

    /**
     * Mock connector.
     *
     * @var \Icicle\Socket\Client\ConnectorInterface
     */
    protected $connector;

    /**
     * Mock socket client.
     *
     * @var \Icicle\Socket\Client\ClientInterface
     */
    protected $client;

    /**
     * @var \Icicle\Dns\Executor\Executor
     */
    protected $executor;

    public function setUp()
    {
        $this->client = $this->createClient();

        $this->connector = $this->createConnector($this->client);

        $this->executor = new Executor(self::ADDRESS, self::PORT, $this->connector);
    }

    /**
     * @return \Icicle\Socket\Client\ClientInterface
     */
    public function createClient()
    {
        $mock = Mockery::mock('Icicle\Socket\Client\ClientInterface');

        $mock->shouldReceive('close');

        return $mock;
    }

    /**
     * @param \Icicle\Socket\Client\ClientInterface $client
     *
     * @return \Icicle\Socket\Client\ConnectorInterface
     */
    public function createConnector(ClientInterface $client)
    {
        $mock = Mockery::mock('Icicle\Socket\Client\ConnectorInterface');

        $mock->shouldReceive('connect')->andReturn($client);

        return $mock;
    }

    public function testGetAddress()
    {
        $this->assertSame(self::ADDRESS, $this->executor->getAddress());
    }

    public function testGetPort()
    {
        $this->assertSame(self::PORT, $this->executor->getPort());
    }

    public function testInvalidIntegerType()
    {
        $type = -1;

        $coroutine = new Coroutine($this->executor->execute('example.com', $type));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\InvalidTypeException'))
            ->will($this->returnCallback(function (InvalidTypeException $exception) use ($type) {
                $this->assertSame($type, $exception->getType());
            }));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testInvalidStringType()
    {
        $type = 'Q';

        $coroutine = new Coroutine($this->executor->execute('example.com', $type));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\InvalidTypeException'))
            ->will($this->returnCallback(function (InvalidTypeException $exception) use ($type) {
                $this->assertSame($type, $exception->getType());
            }));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function execute($type, $domain, $request, $response, array $answers = null, array $authority = null)
    {
        $this->client->shouldReceive('write')
            ->andReturnUsing(function ($data) use (&$id, $request) {
                $id = substr($data, 0, self::ID_LENGTH);
                $this->assertSame(substr(base64_decode($request), self::ID_LENGTH), substr($data, self::ID_LENGTH));
                return strlen($data);
            });

        $this->client->shouldReceive('read')
            ->andReturnUsing(function () use (&$id, $response) {
                return Promise\resolve($id . substr(base64_decode($response), self::ID_LENGTH));
            });

        $coroutine = new Coroutine($this->executor->execute($domain, $type));

        $callback = function (Message $message) use ($answers, $authority) {
            $this->assertInstanceOf('LibDNS\Messages\Message', $message);

            if (null !== $answers) {
                /** @var \LibDNS\Records\Resource $record */
                foreach ($message->getAnswerRecords() as $key => $record) {
                    $this->assertSame($answers[$key]['type'], $record->getType());
                    $this->assertSame($answers[$key]['ttl'], $record->getTTL());
                    $this->assertSame($answers[$key]['value'], (string) $record->getData());
                }
            }

            if (null !== $authority) {
                /** @var \LibDNS\Records\Resource $record */
                foreach ($message->getAuthorityRecords() as $key => $record) {
                    $this->assertSame($authority[$key]['type'], $record->getType());
                    $this->assertSame($authority[$key]['ttl'], $record->getTTL());
                    $this->assertSame($authority[$key]['value'], (string) $record->getData());
                }
            }
        };

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

    /**
     * @dataProvider getInvalid
     */
    public function testInvalid($domain, $request, $response)
    {
        $this->client->shouldReceive('write')
            ->andReturnUsing(function ($data) use (&$id, $request) {
                $id = substr($data, 0, self::ID_LENGTH);
                $this->assertSame(substr(base64_decode($request), self::ID_LENGTH), substr($data, self::ID_LENGTH));
                return strlen($data);
            });

        $this->client->shouldReceive('read')
            ->andReturnUsing(function () use (&$id, $response) {
                return Promise\resolve($id . substr(base64_decode($response), 2));
            });

        $coroutine = new Coroutine($this->executor->execute($domain, 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\ExceptionInterface'));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @dataProvider getNsRecords
     */
    public function testInvalidResponseId($domain, $request, $response)
    {
        $this->client->shouldReceive('write')
            ->andReturnUsing(function ($data) use (&$id, $request) {
                $id = substr($data, 0, self::ID_LENGTH);
                $this->assertSame(substr(base64_decode($request), self::ID_LENGTH), substr($data, self::ID_LENGTH));
                return strlen($data);
            });

        $this->client->shouldReceive('read')
            ->andReturnUsing(function () use (&$id, $response) {
                $id = unpack('n', $id)[1];
                $id += 1;
                $id = pack('n', $id);
                return Promise\resolve($id . substr(base64_decode($response), self::ID_LENGTH));
            });

        $coroutine = new Coroutine($this->executor->execute($domain, 'NS'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\ResponseIdException'))
            ->will($this->returnCallback(function (ResponseIdException $exception) use (&$id) {
                $response = $exception->getResponse();
                $this->assertInstanceOf('LibDNS\Messages\Message', $response);
            }));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testEmptyStringReceivedAsResponse()
    {
        $this->client->shouldReceive('write')
            ->andReturnUsing(function ($data) {
                return Promise\resolve(strlen($data));
            });

        $this->client->shouldReceive('read')
            ->andReturn(Promise\resolve(''));

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A'));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\FailureException'));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @depends testEmptyStringReceivedAsResponse
     */
    public function testInvalidRetryCount()
    {
        $this->client->shouldReceive('write')
            ->andReturnUsing(function ($data) {
                return Promise\resolve(strlen($data));
            });

        $this->client->shouldReceive('read')
            ->andReturn(Promise\resolve(''));

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A', 1, -1));

        $coroutine->done($this->createCallback(0), $this->createCallback(1));

        Loop\run();
    }

    public function testServerDoesNotRespond()
    {
        $timeout = 0.1;

        $this->client->shouldReceive('write')
            ->andReturnUsing(function ($data) {
                return Promise\resolve(strlen($data));
            });

        $this->client->shouldReceive('read')
            ->andReturn(Promise\reject(new TimeoutException('Socket timed out.')));

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A', $timeout, 0));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\NoResponseException'));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testServerDoesNotRespondWithRetries()
    {
        $timeout = 0.1;
        $retries = 3;

        $this->client->shouldReceive('write')
            ->andReturnUsing(function ($data) {
                return Promise\resolve(strlen($data));
            });

        $this->client->shouldReceive('read')
            ->times(3)
            ->andReturn(Promise\reject(new TimeoutException('Socket timed out.')));

        $coroutine = new Coroutine($this->executor->execute('example.com', 'A', $timeout, $retries));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\NoResponseException'));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @dataProvider getARecords
     */
    public function testServerRespondsAfterRetry($domain, $request, $response)
    {
        $timeout = 0.1;
        $retries = 3;

        $this->client->shouldReceive('write')
            ->times($retries)
            ->andReturnUsing(function ($data) use (&$id, $request) {
                $id = substr($data, 0, self::ID_LENGTH);
                $this->assertSame(substr(base64_decode($request), self::ID_LENGTH), substr($data, self::ID_LENGTH));
                return strlen($data);
            });

        $this->client->shouldReceive('read')
            ->times($retries)
            ->andReturnUsing(function () use (&$id, $response) {
                static $initial = true;
                if ($initial) {
                    $initial = false;
                    return Promise\reject(new TimeoutException('Socket timed out.'));
                }
                return Promise\resolve($id . substr(base64_decode($response), self::ID_LENGTH));
            });

        $coroutine = new Coroutine($this->executor->execute($domain, 'A', $timeout, $retries));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('LibDNS\Messages\Message'));

        $coroutine->done($callback);

        Loop\run();
    }
}
