<?php
namespace Icicle\Dns\Resolver;

use Exception;
use LibDNS\Records\RecordCollection;
use LibDNS\Records\ResourceTypes;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Query\Query;
use Icicle\Promise\Promise;

class Resolver implements ResolverInterface
{
    /**
     * @var ExecutorInterface
     */
    private $executor;
    
    /**
     * @param   ExecutorInterface $executor
     */
    public function __construct(ExecutorInterface $executor)
    {
        $this->executor = $executor;
    }
    
    /**
     * @param   string $domain Domain name to resolve.
     * @param   int|float $timeout Maximum time until request fails.
     *
     * @return  Icicle\Promise\PromiseInterface
     *
     * @resolve string Resolved IP address.
     *
     * @reject  Icicle\Dns\Query\Execption\FailureException If the server returns a non-zero response code.
     * @reject  Icicle\Dns\Query\Execption\NotFoundException If the domain cannot be resolved.
     */
    public function resolve($domain, $timeout = ExecutorInterface::DEFAULT_TIMEOUT)
    {
        try {
            $query = new Query($domain, ResourceTypes::A);
        } catch (Exception $e) {
            return Promise::reject($exception);
        }
        
        return $this->executor->execute($query, $timeout)
            ->then(function (RecordCollection $answers) {
                foreach (clone $answers as $record) {
                    if (ResourceTypes::CNAME === $record->getType()) {
                        $answers->remove($record);
                    }
                }
                
                $count = count($answers);
                
                if (1 < $count) {
                    return $answers->getRecordByIndex(mt_rand(0, $count - 1))->getData();
                }
                
                return $answers->getRecordByIndex(0)->getData();
            });
    }
}
