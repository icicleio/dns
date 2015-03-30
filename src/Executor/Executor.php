<?php
namespace Icicle\Dns\Executor;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\FailureException;
use Icicle\Dns\Exception\NotFoundException;
use Icicle\Dns\Query\QueryInterface;
use Icicle\Socket\Client\Connector;
use Icicle\Socket\Exception\TimeoutException;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Decoder\DecoderFactory;

class Executor implements ExecutorInterface
{
    const PROTOCOL = 'udp';
    const PORT = 53;
    const MAX_PACKET_SIZE = 512;
    
    /**
     * @var string IP address of DNS server.
     */
    private $nameserver;
    
    /**
     * @var \LibDNS\Records\QuestionFactory
     */
    private $questionFactory;
    
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
    
    private $connector;
    
    /**
     * @param   string $nameserver Nameserver IP address to resolve queries.
     */
    public function __construct($nameserver)
    {
        $this->nameserver = $nameserver;
        
        $this->questionFactory = new QuestionFactory();
        $this->messageFactory = new MessageFactory();
        
        $this->encoder = (new EncoderFactory())->create();
        $this->decoder = (new DecoderFactory())->create();
        
        $this->connector = new Connector();
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
     * @coroutine
     *
     * @param   \Icicle\Dns\Query\QueryInterface $query
     * @param   float|int $timeout
     * @param   int $retries
     *
     * @return  \Generator
     *
     * @resolve \LibDNS\Records\RecordCollection
     */
    protected function run(QueryInterface $query, $timeout, $retries)
    {
        $question = $this->questionFactory->create($query->getType());
        $question->setName($query->getDomain());

        $request = $this->messageFactory->create(MessageTypes::QUERY);
        $request->getQuestionRecords()->add($question);
        $request->isRecursionDesired(true);

        /** @var \Icicle\Socket\Client\ClientInterface $client */
        $client = (yield $this->connector->connect($this->nameserver, self::PORT, ['protocol' => self::PROTOCOL]));

        $request = $this->encoder->encode($request);

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
        } while ($attempt++ < $retries);

        throw new FailureException('Server did not respond to query.');
    }
}
