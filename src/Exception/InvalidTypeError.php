<?php
namespace Icicle\Dns\Exception;

class InvalidTypeError extends Error
{
    /**
     * @var int|string
     */
    private $type;

    /**
     * @param int|string $type
     */
    public function __construct($type)
    {
        if (is_int($type)) {
            $message = sprintf('%d does not correspond to a valid record type (must be between 0 and 65535).', $type);
        } else {
            $message = sprintf('"%s" does not name a valid record type.', $type);
        }
        
        parent::__construct($message);
        
        $this->type = $type;
    }

    /**
     * @return int|string
     */
    public function getType()
    {
        return $this->type;
    }
}
