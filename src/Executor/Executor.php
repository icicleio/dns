<?php
namespace Icicle\Dns\Executor;

use Icicle\Dns\Exception\FailureException;
use Icicle\Dns\Exception\InvalidTypeError;
use Icicle\Dns\Exception\NoResponseException;
use Icicle\Dns\Exception\ResponseCodeException;
use Icicle\Dns\Exception\ResponseIdException;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Socket\Client\Connector as ClientConnector;
use Icicle\Socket\Client\ConnectorInterface as ClientConnectorInterface;
use LibDNS\Messages\Message;
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
     * @param string $address Name server IP address to resolve queries.
     * @param int $port
     * @param \Icicle\Socket\Client\ConnectorInterface|null $connector
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
    public function execute(
        string $name,
        $type,
        float $timeout = self::DEFAULT_TIMEOUT,
        int $retries = self::DEFAULT_RETRIES
    ): \Generator {
        $retries = (int) $retries;
        if (0 > $retries) {
            $retries = 0;
        }

        $question = $this->createQuestion($name, $type);

        $request = $this->createRequest($question);

        $data = $this->encoder->encode($request);

        /** @var \Icicle\Socket\Client\ClientInterface $client */
        $client = yield $this->connector->connect($this->address, $this->port, ['protocol' => self::PROTOCOL]);

        try {
            $attempt = 0;

            do {
                try {
                    yield $client->write($data);

                    $response = yield $client->read(self::MAX_PACKET_SIZE, null, $timeout);

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

                    return $response;
                } catch (TimeoutException $exception) {
                    // Ignore timeout and try the request again.
                }
            } while (++$attempt <= $retries);

            throw new NoResponseException('No response from server.');
        } finally {
            $client->close();
        }
    }

    /**
     * IP address of the name server used by this executor.
     *
     * @return  string
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @return  int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param   string $name
     * @param   string|int $type
     *
     * @return \LibDNS\Records\Question
     *
     * @throws \Icicle\Dns\Exception\InvalidTypeError If the record type given is invalid.
     */
    protected function createQuestion(string $name, $type): Question
    {
        if (!is_int($type)) {
            $type = strtoupper($type);
            $types = static::getRecordTypes();
            if (!array_key_exists($type, $types)) {
                throw new InvalidTypeError($type);
            }
            $type = $types[$type];
        } elseif (0 > $type || 0xffff < $type) {
            throw new InvalidTypeError($type);
        }

        $question = $this->questionFactory->create($type);
        $question->setName($name);

        return $question;
    }

    /**
     * @param \LibDNS\Records\Question
     *
     * @return \LibDNS\Messages\Message
     */
    protected function createRequest(Question $question): Message
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
     * @return int
     */
    protected function createId(): int
    {
        return mt_rand(0, 0xffff);
    }

    /**
     * @return int[]
     */
    protected static function getRecordTypes(): array
    {
        return self::$recordTypes;
    }
}
