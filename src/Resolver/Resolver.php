<?php
namespace Icicle\Dns\Resolver;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Exception\NotFoundException;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Query\Query;
use LibDNS\Records\ResourceQTypes;

class Resolver implements ResolverInterface
{
    /**
     * @var \Icicle\Dns\Executor\ExecutorInterface
     */
    private $executor;
    
    /**
     * @param   \Icicle\Dns\Executor\ExecutorInterface $executor
     */
    public function __construct(ExecutorInterface $executor)
    {
        $this->executor = $executor;
    }
    
    /**
     * @inheritdoc
     */
    public function resolve(
        $domain,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        $retries = ExecutorInterface::DEFAULT_RETRIES
    ) {
        return new Coroutine($this->run($domain, $timeout, $retries));
    }

    /**
     * @param   string $domain
     * @param   float|int $timeout
     * @param   int $retries
     *
     * @return  \Generator
     */
    protected function run($domain, $timeout, $retries)
    {
        $query = new Query($domain, ResourceQTypes::A);

        $answers = (yield $this->executor->execute($query, $timeout, $retries));

        $result = [];
        $type = $query->getType();

        foreach ($answers as $record) {
            /** @var \LibDNS\Records\Resource $record */
            // Skip any CNAME or other records returned in result.
            if ($record->getType() === $type) {
                $result[] = $record->getData();
            }
        }

        if (0 === count($result)) {
            throw new NotFoundException($query);
        }

        yield $result;
    }
}
