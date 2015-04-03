<?php
namespace Icicle\Dns\Executor;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\FailureException;
use Icicle\Dns\Exception\NotFoundException;
use Icicle\Dns\Query\QueryInterface;
use Icicle\Socket\Client\Connector;
use Icicle\Socket\Client\ConnectorInterface;
use Icicle\Socket\Exception\TimeoutException;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Decoder\DecoderFactory;

class Executor implements ExecutorInterface
{
    const PROTOCOL = 'udp';
    const DEFAULT_PORT = 53;
    const MAX_PACKET_SIZE = 512;
    
    /**
     * @var string IP address of DNS server.
     */
    private $address;

    /**
     * @var int
     */
    private $port;

    /**
     * @var \LibDNS\Messages\MessageFactory
     */
    private $messageFactory;
    
    /**
     * @var \LibDNS\Encoder\Encoder
     */
    private $encoder;
    
    /**
     * @var \LibDNS\Decoder\Decoder
     */
    private $decoder;

    /**
     * @var \Icicle\Socket\Client\ConnectorInterface
     */
    private $connector;
    
    /**
     * @param   string $address Nameserver IP address to resolve queries.
     * @param   int $port
     * @param   \Icicle\Socket\Client\ConnectorInterface|null $connector
     */
    public function __construct($address, $port = self::DEFAULT_PORT, ConnectorInterface $connector = null)
    {
        $this->address = $address;
        $this->port = $port;
        
        $this->messageFactory = new MessageFactory();
        
        $this->encoder = (new EncoderFactory())->create();
        $this->decoder = (new DecoderFactory())->create();

        $this->connector = $connector;

        if (null === $this->connector) {
            $this->connector = new Connector();
        }
    }
    
    /**
     * @inheritdoc
     */
    public function execute(QueryInterface $query, $timeout = self::DEFAULT_TIMEOUT, $retries = self::DEFAULT_RETRIES)
    {
        $retries = (int) $retries;
        if (0 > $retries) {
            $retries = 0;
        }

        return new Coroutine($this->run($query, $timeout, $retries));
    }

    /**
     * IP address of the nameserver used by this executor.
     *
     * @return  string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return  int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @coroutine
     *
     * @param   \Icicle\Dns\Query\QueryInterface $query
     * @param   float|int $timeout
     * @param   int $retries
     *
     * @return  \Generator
     *
     * @resolve \LibDNS\Records\RecordCollection
     *
     * @reject  \Icicle\Dns\Exception\FailureException If the server responds with a non-zero response code or does
     *          not respond at all.
     * @reject  \Icicle\Dns\Exception\NotFoundException If a record for the given query is not found.
     */
    protected function run(QueryInterface $query, $timeout, $retries)
    {
        /** @var \Icicle\Socket\Client\ClientInterface $client */
        $client = (yield $this->connect());

        $request = $this->encoder->encode($this->createRequest($query));

        $attempt = 0;

        do {
            try {
                $client->write($request);

                $response = $this->decoder->decode(
                    yield $client->read(self::MAX_PACKET_SIZE, null, $timeout)
                );

                if (0 !== $response->getResponseCode()) {
                    throw new FailureException("Server returned response code {$response->getResponseCode()}.");
                }

                $answers = $response->getAnswerRecords();

                if (0 === count($answers)) {
                    throw new NotFoundException($query);
                }

                yield $answers;
                return;
            } catch (TimeoutException $exception) {
                // Ignore TimeoutException and try the request again.
            }
        } while (++$attempt <= $retries);

        throw new FailureException('Server did not respond to query.');
    }

    /**
     * @param   \Icicle\Dns\Query\QueryInterface $query
     *
     * @return  \LibDNS\Messages\Message
     */
    protected function createRequest(QueryInterface $query)
    {
        $request = $this->messageFactory->create(MessageTypes::QUERY);
        $request->getQuestionRecords()->add($query->getQuestion());
        $request->isRecursionDesired(true);

        return $request;
    }

    /**
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @see     \Icicle\Socket\Client\ConnectorInterface::connect()
     */
    protected function connect()
    {
        return $this->connector->connect($this->address, $this->port, ['protocol' => self::PROTOCOL]);
    }
}
