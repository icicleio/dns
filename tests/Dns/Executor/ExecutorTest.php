<?php
namespace Icicle\Tests\Dns\Executor;

use Icicle\Dns\Executor\Executor;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Tests\TestCase;
use LibDNS\Messages\Message;
use Symfony\Component\Yaml\Yaml;

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
     * @return  \Icicle\Socket\Client\ClientInterface
     */
    public function createClient()
    {
        return $this->getMock('Icicle\Socket\Client\ClientInterface');
    }

    /**
     * @param   \Icicle\Socket\Client\ClientInterface $client
     *
     * @return  \Icicle\Socket\Client\ConnectorInterface
     */
    public function createConnector(ClientInterface $client)
    {
        $mock = $this->getMock('Icicle\Socket\Client\ConnectorInterface');

        $mock->method('connect')
            ->will($this->returnValue(Promise::resolve($client)));

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

    public function testInvalidType()
    {
        $promise = $this->executor->execute('example.com', 'Q');

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\InvalidTypeException'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }

    public function execute($type, $domain, $request, $response, $answers = null, $authority = null)
    {
        $this->client->expects($this->once())
            ->method('write')
            ->will($this->returnCallback(function ($data) use (&$id, $request) {
                $id = substr($data, 0, self::ID_LENGTH);
                $this->assertSame(substr(base64_decode($request), self::ID_LENGTH), substr($data, self::ID_LENGTH));
                return strlen($data);
            }));

        $this->client->expects($this->once())
            ->method('read')
            ->will($this->returnCallback(function () use (&$id, $response) {
                return Promise::resolve($id . substr(base64_decode($response), self::ID_LENGTH));
            }));

        $promise = $this->executor->execute($domain, $type);

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

        $promise->done($callback, $this->createCallback(0));

        Loop::run();
    }

    /**
     * @return  array Array of A record requests and responses.
     */
    public function getARecords()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/a.yml'));
    }

    /**
     * @dataProvider getARecords
     */
    public function testARecords($domain, $request, $response, $answers = null, $authority = null)
    {
        $this->execute('A', $domain, $request, $response, $answers, $authority);
    }

    /**
     * @return  array Array of MX record requests and responses.
     */
    public function getMxRecords()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/mx.yml'));
    }

    /**
     * @dataProvider getMxRecords
     */
    public function testMxRecords($domain, $request, $response, $answers = null, $authority = null)
    {
        $this->execute('MX', $domain, $request, $response, $answers, $authority);
    }

    /**
     * @return  array Array of NS record requests and responses.
     */
    public function getNsRecords()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/ns.yml'));
    }

    /**
     * @dataProvider getNsRecords
     */
    public function testNsRecords($domain, $request, $response, $answers = null, $authority = null)
    {
        $this->execute('NS', $domain, $request, $response, $answers, $authority);
    }

    /**
     * @return  array Array of invalid record requests and responses.
     */
    public function getInvalid()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/invalid.yml'));
    }

    /**
     * @dataProvider getInvalid
     */
    public function testInvalid($domain, $request, $response)
    {
        $this->client->expects($this->once())
            ->method('write')
            ->will($this->returnCallback(function ($data) use (&$id, $request) {
                $id = substr($data, 0, self::ID_LENGTH);
                $this->assertSame(substr(base64_decode($request), self::ID_LENGTH), substr($data, self::ID_LENGTH));
                return strlen($data);
            }));

        $this->client->expects($this->once())
            ->method('read')
            ->will($this->returnCallback(function () use (&$id, $response) {
                return Promise::resolve($id . substr(base64_decode($response), 2));
            }));

        $promise = $this->executor->execute($domain, 'A');

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\ExceptionInterface'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }

    public function testEmptyStringReceivedAsResponse()
    {
        $this->client->expects($this->once())
            ->method('read')
            ->will($this->returnValue(Promise::resolve('')));

        $promise = $this->executor->execute('example.com', 'A');

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\FailureException'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }


    /**
     * @depends testEmptyStringReceivedAsResponse
     */
    public function testInvalidRetryCount()
    {
        $this->client->expects($this->once())
            ->method('read')
            ->will($this->returnValue(Promise::resolve('')));

        $promise = $this->executor->execute('example.com', 'A', 1, -1);

        $promise->done($this->createCallback(0), $this->createCallback(1));

        Loop::run();
    }

    public function testServerDoesNotRespond()
    {
        $timeout = 0.1;

        $this->client->expects($this->once())
            ->method('read')
            ->will($this->returnValue(Promise::reject(new TimeoutException('Socket timed out.'))));

        $promise = $this->executor->execute('example.com', 'A', $timeout, 0);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\NoResponseException'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }

    public function testServerDoesNotRespondWithRetries()
    {
        $timeout = 0.1;
        $retries = 3;

        $this->client->expects($this->exactly($retries + 1))
            ->method('read')
            ->will($this->returnValue(Promise::reject(new TimeoutException('Socket timed out.'))));

        $promise = $this->executor->execute('example.com', 'A', $timeout, $retries);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Dns\Exception\NoResponseException'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }

    /**
     * @dataProvider getARecords
     */
    public function testServerRespondsAfterRetry($domain, $request, $response)
    {
        $this->client->method('write')
            ->will($this->returnCallback(function ($data) use (&$id, $request) {
                $id = substr($data, 0, self::ID_LENGTH);
                $this->assertSame(substr(base64_decode($request), self::ID_LENGTH), substr($data, self::ID_LENGTH));
                return strlen($data);
            }));

        $this->client->method('read')
            ->will($this->returnCallback(function () use (&$id, $response) {
                static $initial = true;
                if ($initial) {
                    $initial = false;
                    return Promise::reject(new TimeoutException('Socket timed out.'));
                }
                return Promise::resolve($id . substr(base64_decode($response), self::ID_LENGTH));
            }));

        $promise = $this->executor->execute($domain, 'A');

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('LibDNS\Messages\Message'));

        $promise->done($callback);

        Loop::run();
    }
}
