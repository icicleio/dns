<?php
namespace Icicle\Dns\Exception;

class NotFoundException extends Exception
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $type;

    /**
     * @param string $name
     * @param int $type
     */
    public function __construct($name, $type)
    {
        parent::__construct("Record of type {$type} not found for {$name}.");

        $this->name = $name;
        $this->type = $type;
    }

    /**
     * @return string Domain name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }
}
