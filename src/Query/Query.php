<?php
namespace Icicle\Dns\Query;

use Icicle\Dns\Exception\InvalidTypeException;
use LibDNS\Records\Types\DomainName;

class Query implements QueryInterface
{
    /**
     * @var \LibDNS\Records\Types\DomainName
     */
    private $name;
    
    /**
     * @var int
     */
    private $type;

    /**
     * @param   string $name Domain name.
     * @param   string $type Query type, such as 'A', 'AAAA', 'MX', etc.
     *
     * @throws  \Icicle\Dns\Exception\InvalidTypeException If the given type is invalid.
     * @throws  \UnexpectedValueException If the given name is not a valid domain name.
     */
    public function __construct($name, $type)
    {
        if (is_int($type)) {
            $this->type = $type;
        } else {
            $type = strtoupper($type);
            // Error reporting suppressed since constant() emits an E_WARNING if constant not found, checked below.
            $value = @constant('\LibDNS\Records\ResourceQTypes::'.$type);
            if (null === $value) {
                throw new InvalidTypeException($type);
            }
            $this->type = $value;
        }
        
        $this->name = new DomainName($name);
    }
    
    /**
     * @inheritdoc
     */
    public function getDomain()
    {
        return $this->name;
    }
    
    /**
     * @inheritdoc
     */
    public function getType()
    {
        return $this->type;
    }
}
