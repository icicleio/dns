<?php
namespace Icicle\Dns\Resolver;

use Exception;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Query\Query;
use Icicle\Promise\Promise;
use LibDNS\Records\RecordCollection;
use LibDNS\Records\ResourceTypes;

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
     * @inheritdoc
     */
    public function resolve(
        $domain,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        $retries = ExecutorInterface::DEFAULT_RETRIES
    ) {
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
