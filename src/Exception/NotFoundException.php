<?php
namespace Icicle\Dns\Exception;

use Icicle\Dns\Query\QueryInterface;

class NotFoundException extends RuntimeException
{
    /**
     * @var \Icicle\Dns\Query\QueryInterface
     */
    private $query;

    /**
     * @param   \Icicle\Dns\Query\QueryInterface $query
     */
    public function __construct(QueryInterface $query)
    {
        parent::__construct("Record of type {$query->getType()} not found for {$query->getDomain()}.");
        
        $this->query = $query;
    }

    /**
     * @return  \Icicle\Dns\Query\QueryInterface
     */
    public function getQuery()
    {
        return $this->query;
    }
}
