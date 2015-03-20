<?php
namespace Icicle\Dns\Executor;

use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\ResourceQTypes;
use LibDNS\Records\ResourceTypes;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Decoder\DecoderFactory;
use Icicle\Dns\Executor\Exception\FailureException;
use Icicle\Dns\Executor\Exception\NotFoundException;
use Icicle\Dns\Query\QueryInterface;
use Icicle\Socket\Client;

class Executor implements ExecutorInterface
{
    const PROTOCOL = 'udp';
    const PORT = 53;
    
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
    }
    
    /**
     * @param   string $domain Domain name to resolve.
     * @param   int|float $timeout Maximum time until request fails.
     *
     * @return  Icicle\Promise\PromiseInterface
     *
     * @resolve LibDNS\Records\RecordCollection
     *
     * @reject  Icicle\Dns\Executor\Exception\FailureException If the server returns a non-zero response code.
     * @reject  Icicle\Dns\Executor\Exception\NotFoundException If the domain cannot be resolved.
     */
    public function execute(QueryInterface $query, $timeout = self::DEFAULT_TIMEOUT)
    {
        $question = $this->questionFactory->create($query->getType());
        $question->setName($query->getName());
        
        $request = $this->messageFactory->create(MessageTypes::QUERY);
        $request->getQuestionRecords()->add($question);
        $request->isRecursionDesired(true);
        
        return Client::connect($this->nameserver, self::PORT, ['protocol' => self::PROTOCOL])
            ->then(function ($client) use ($request, $timeout) {
                $client->write($this->encoder->encode($request));
                return $client->read(512, $timeout);
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
