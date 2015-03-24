<?php
namespace Icicle\Dns\Executor;

use Exception;
use Icicle\Dns\Exception\FailureException;
use Icicle\Dns\Exception\NotFoundException;
use Icicle\Dns\Query\QueryInterface;
use Icicle\Promise\Promise;
use Icicle\Socket\Client\Connector;
use Icicle\Socket\Exception\TimeoutException;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\ResourceQTypes;
use LibDNS\Records\ResourceTypes;
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
     * @var LibDNS\Records\QuestionFactory
     */
    private $questionFactory;
    
    /**
     * @var LibDNS\Records\MessageFactory
     */
    private $messageFactory;
    
    /**
     * @var LibDNS\Encoder\Encoder
     */
    private $encoder;
    
    /**
     * @var LibDNS\Decoder\Decoder
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
        $question = $this->questionFactory->create($query->getType());
        $question->setName($query->getDomain());
        
        $request = $this->messageFactory->create(MessageTypes::QUERY);
        $request->getQuestionRecords()->add($question);
        $request->isRecursionDesired(true);
        
        $retries = (int) $retries;
        if (0 > $retries) {
            $retries = 0;
        }
        
        return $this->connector->connect($this->nameserver, self::PORT, ['protocol' => self::PROTOCOL])
            ->then(function ($client) use ($request, $timeout, $retries) {
                $request = $this->encoder->encode($request);
                
                if (0 === $retries) {
                    $client->write($request);
                    return $client->read(self::MAX_PACKET_SIZE, $timeout);
                }
                
                return Promise::retry(
                    function () use ($client, $request, $timeout) {
                        $client->write($request);
                        return $client->read(self::MAX_PACKET_SIZE, $timeout);
                    },
                    function (Exception $exception) use ($retries) {
                        static $attempt = 0;
                        if (++$attempt > $retries || !$exception instanceof TimeoutException) {
                            return true;
                        }
                        return false;
                    }
                );
            })
            ->then(function ($data) use ($query) {
                $response = $this->decoder->decode($data);
                
                if (0 !== $response->getResponseCode()) {
                    throw new FailureException("Server returned response code {$response->getResponseCode()}.");
                }
                
                $answers = $response->getAnswerRecords();
                
                if (0 === count($answers)) {
                    throw new NotFoundException($query);
                }
                
                return $answers;
            });
    }
}
