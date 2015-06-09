<?php
namespace Icicle\Dns\Executor;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\FailureException;
use Icicle\Dns\Exception\InvalidTypeException;
use Icicle\Dns\Exception\NoResponseException;
use Icicle\Dns\Exception\ResponseCodeException;
use Icicle\Dns\Exception\ResponseIdException;
use Icicle\Socket\Client\Connector as ClientConnector;
use Icicle\Socket\Client\ConnectorInterface as ClientConnectorInterface;
use Icicle\Socket\Exception\TimeoutException;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Decoder\DecoderFactory;
use LibDNS\Records\Question;
use LibDNS\Records\QuestionFactory;

class Executor implements ExecutorInterface
{
    const PROTOCOL = 'udp';
    const DEFAULT_PORT = 53;
    const MAX_PACKET_SIZE = 512;

    private static $recordTypes = [
        'A'          => 1,
        'AAAA'       => 28,
        'ALL'        => 255,
        'AFSDB'      => 18,
        'ANY'        => 255,
        'APL'        => 42,
        'AXFR'       => 252,
        'CAA'        => 257,
        'CDNSKEY'    => 60,
        'CDS'        => 59,
        'CERT'       => 37,
        'CNAME'      => 5,
        'DHCID'      => 49,
        'DLV'        => 32769,
        'DNAME'      => 39,
        'DNSKEY'     => 48,
        'DS'         => 43,
        'HIP'        => 55,
        'IPSECKEY'   => 45,
        'IXFR'       => 251,
        'KEY'        => 25,
        'KX'         => 36,
        'LOC'        => 29,
        'MAILB'      => 253,
        'MAILA'      => 254,
        'MX'         => 15,
        'NAPTR'      => 35,
        'NS'         => 2,
        'NSEC'       => 47,
        'NSEC3'      => 50,
        'NSEC3PARAM' => 51,
        'OPT'        => 41,
        'PTR'        => 12,
        'RRSIG'      => 46,
        'SIG'        => 24,
        'SOA'        => 6,
        'SRV'        => 33,
        'SSHFP'      => 44,
        'TA'         => 32768,
        'TKEY'       => 249,
        'TLSA'       => 52,
        'TSIG'       => 250,
        'TXT'        => 16,
        '*'          => 255,
    ];

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
     * @var \LibDNS\Records\QuestionFactory
     */
    private $questionFactory;
    
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
     * @param   string $address Name server IP address to resolve queries.
     * @param   int $port
     * @param   \Icicle\Socket\Client\ConnectorInterface|null $connector
     */
    public function __construct($address, $port = self::DEFAULT_PORT, ClientConnectorInterface $connector = null)
    {
        $this->address = $address;
        $this->port = $port;
        
        $this->messageFactory = new MessageFactory();
        $this->questionFactory = new QuestionFactory();

        $this->encoder = (new EncoderFactory())->create();
        $this->decoder = (new DecoderFactory())->create();

        $this->connector = $connector ?: new ClientConnector();
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute($name, $type, $timeout = self::DEFAULT_TIMEOUT, $retries = self::DEFAULT_RETRIES)
    {
        $retries = (int) $retries;
        if (0 > $retries) {
            $retries = 0;
        }

        return new Coroutine($this->run($name, $type, $timeout, $retries));
    }

    /**
     * IP address of the name server used by this executor.
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
     * @param   string $name Domain name.
     * @param   string|int $type Record type (e.g., 'A', 'MX', 'AAAA', 'NS' or integer value of type)
     * @param   float|int $timeout Seconds until a request times out.
     * @param   int $retries Number of times to attempt the request.
     *
     * @return  \Generator
     *
     * @resolve \LibDNS\Messages\Message
     *
     * @reject  \Icicle\Dns\Exception\FailureException If the server responds with a non-zero response code or does
     *          not respond at all.
     * @reject  \Icicle\Dns\Exception\NotFoundException If a record for the given query is not found.
     * @reject  \Icicle\Dns\Exception\MessageException If there is a problem with the response or no response.
     */
    protected function run($name, $type, $timeout, $retries)
    {
        $question = $this->createQuestion($name, $type);

        $request = $this->createRequest($question);

        $data = $this->encoder->encode($request);

        /** @var \Icicle\Socket\Client\ClientInterface $client */
        $client = (yield $this->connector->connect($this->address, $this->port, ['protocol' => self::PROTOCOL]));

        try {
            $attempt = 0;

            do {
                try {
                    yield $client->write($data);

                    $response = (yield $client->read(self::MAX_PACKET_SIZE, null, $timeout));

                    try {
                        $response = $this->decoder->decode($response);
                    } catch (\Exception $exception) {
                        throw new FailureException($exception); // Wrap in more specific exception.
                    }

                    if (0 !== $response->getResponseCode()) {
                        throw new ResponseCodeException($response);
                    }

                    if ($response->getId() !== $request->getId()) {
                        throw new ResponseIdException($response);
                    }

                    yield $response;
                    return;
                } catch (TimeoutException $exception) {
                    // Ignore TimeoutException and try the request again.
                }
            } while (++$attempt <= $retries);

            throw new NoResponseException('No response from server.');
        } finally {
            $client->close();
        }
    }

    /**
     * @param   string $name
     * @param   string|int $type
     *
     * @return  \LibDNS\Records\Question
     */
    protected function createQuestion($name, $type)
    {
        if (!is_int($type)) {
            $type = strtoupper($type);
            $types = static::getRecordTypes();
            if (!array_key_exists($type, $types)) {
                throw new InvalidTypeException($type);
            }
            $type = $types[$type];
        } elseif (0 > $type || 0xffff < $type) {
            throw new InvalidTypeException($type);
        }

        $question = $this->questionFactory->create($type);
        $question->setName($name);

        return $question;
    }

    /**
     * @param   \LibDNS\Records\Question
     *
     * @return  \LibDNS\Messages\Message
     */
    protected function createRequest(Question $question)
    {
        $request = $this->messageFactory->create(MessageTypes::QUERY);
        $request->getQuestionRecords()->add($question);
        $request->isRecursionDesired(true);

        $request->setID($this->createId());

        return $request;
    }

    /**
     * Creates message ID.
     *
     * @return  int
     */
    protected function createId()
    {
        return mt_rand(0, 0xffff);
    }

    /**
     * @return  int[]
     */
    protected static function getRecordTypes()
    {
        return self::$recordTypes;
    }
}
