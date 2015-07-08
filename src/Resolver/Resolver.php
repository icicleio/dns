<?php
namespace Icicle\Dns\Resolver;

use Icicle\Dns\Executor\ExecutorInterface;
use LibDNS\Records\ResourceTypes;

class Resolver implements ResolverInterface
{
    /**
     * @var \Icicle\Dns\Executor\ExecutorInterface
     */
    private $executor;
    
    /**
     * @param \Icicle\Dns\Executor\ExecutorInterface $executor
     */
    public function __construct(ExecutorInterface $executor)
    {
        $this->executor = $executor;
    }
    
    /**
     * {@inheritdoc}
     */
    public function resolve(
        $domain,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        $retries = ExecutorInterface::DEFAULT_RETRIES
    ) {
        if (strtolower($domain) === 'localhost') {
            yield ['127.0.0.1'];
            return;
        }

        /** @var \LibDNS\Messages\Message $response */
        $response = (yield $this->executor->execute($domain, ResourceTypes::A, $timeout, $retries));

        $answers = $response->getAnswerRecords();

        $result = [];

        /** @var \LibDNS\Records\Resource $record */
        foreach ($answers as $record) {
            // Skip any CNAME or other records returned in result.
            if ($record->getType() === ResourceTypes::A) {
                $result[] = $record->getData()->getField(0)->getValue();
            }
        }

        yield $result;
    }
}
