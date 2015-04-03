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
        $question = $query->getQuestion();

        parent::__construct("Record of type {$question->getType()} not found for {$question->getName()}.");
        
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
