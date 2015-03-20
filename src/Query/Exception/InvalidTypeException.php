<?php
namespace Icicle\Dns\Query\Exception;

use Icicle\Dns\Query\QueryInterface;

class NotFoundException extends LogicException
{
    private $type;
    
    public function __construct($type)
    {
        if (is_int($type)) {
            $message = "The integer {$type} does not correspond to a valid record type.";
        } else {
            $message = "{$type} does not name a valid record type.";
        }
        
        parent::__construct($message);
        
        $this->type = $type;
    }
    
    public function getType()
    {
        return $this->type;
    }
}
