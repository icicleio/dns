<?php
namespace Icicle\Dns\Resolver;

use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\ResourceQTypes;
use LibDNS\Records\ResourceTypes;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Decoder\DecoderFactory;

use Icicle\Dns\Resolver\Exception\FailureException;
use Icicle\Dns\Resolver\Exception\NotFoundException;
use Icicle\Socket\Client;

class Resolver
{
    const DEFAULT_TIMEOUT = 10;
    
    private $nameserver;
    
    private $questionFactory;
    
    private $messageFactory;
    
    private $encoder;
    
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
     * @resolve RecordCollection
     *
     * @reject  FailureException If the server returns a non-zero response code.
     * @reject  NotFoundException If the domain cannot be resolved.
     */
    public function resolve($domain, $timeout = self::DEFAULT_TIMEOUT)
    {
        $question = $this->questionFactory->create(ResourceQTypes::A);
        $question->setName($domain);
        
        $request = $this->messageFactory->create(MessageTypes::QUERY);
        $request->getQuestionRecords()->add($question);
        $request->isRecursionDesired(true);
        
        return Client::connect($this->nameserver, 53, ['protocol' => 'udp'])
            ->then(function ($client) use ($request, $timeout) {
                $client->write($this->encoder->encode($request));
                return $client->read(512, $timeout);
            })
            ->then(function ($data) use ($domain) {
                $response = $this->decoder->decode($data);
                
                if (0 !== $response->getResponseCode()) {
                    throw new FailureException("Server returned response code {$response->getResponseCode()}.");
                }
                
                $answers = $response->getAnswerRecords();
                
                foreach (clone $answers as $record) {
                    if (ResourceTypes::CNAME === $record->getType()) {
                        $answers->remove($record);
                    }
                }
                
                if (0 === count($answers)) {
                    throw new NotFoundException($domain);
                }
                
                return $answers;
            });
    }
}
