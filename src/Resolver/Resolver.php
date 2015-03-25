<?php
namespace Icicle\Dns\Resolver;

use Icicle\Dns\Exception\NotFoundException;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Query\Query;
use Icicle\Promise\Promise;
use LibDNS\Records\RecordCollection;
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
        try {
            $query = new Query($domain, ResourceQTypes::A);
        } catch (\Exception $exception) {
            return Promise::reject($exception);
        }
        
        return $this->executor->execute($query, $timeout)
            ->then(function (RecordCollection $answers) use ($query) {
                $result = [];
                $type = $query->getType();
                foreach ($answers as $record) {
                    // Skip any CNAME or other records returned in result.
                    if ($record->getType() === $type) {
                        /** @var \LibDNS\Records\Resource $record */
                        $result[] = $record->getData();
                    }
                }

                if (0 === count($result)) {
                    throw new NotFoundException($query);
                }

                return $result;
            });
    }
}
