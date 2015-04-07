<?php
namespace Icicle\Dns\Exception;

class InvalidTypeException extends InvalidArgumentException
{
    /**
     * @var int|string
     */
    private $type;

    /**
     * @param   int|string $type
     */
    public function __construct($type)
    {
        if (is_int($type)) {
            $message = "{$type} does not correspond to a valid record type (must be between 0 and 65535).";
        } else {
            $message = "'{$type}' does not name a valid record type.";
        }
        
        parent::__construct($message);
        
        $this->type = $type;
    }

    /**
     * @return  int|string
     */
    public function getType()
    {
        return $this->type;
    }
}
