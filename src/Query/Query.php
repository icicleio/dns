<?php
namespace Icicle\Dns\Query;

use LibDNS\Records\Types\DomainName;
use LibDNS\Records\ResourceQTypes;

class Query implements QueryInterface
{
    /**
     * @var string[int]
     */
    private static $types = [
        ResourceQTypes::A =>      'A',
        ResourceQTypes::AAAA =>   'AAAA',
        ResourceQTypes::AFSDB =>  'AFSDB',
        ResourceQTypes::CAA =>    'CAA',
        ResourceQTypes::CNAME =>  'CNAME',
        ResourceQTypes::DHCID =>  'DHCID',
        ResourceQTypes::DLV =>    'DLV',
        ResourceQTypes::DNAME =>  'DNAME',
        ResourceQTypes::DNSKEY => 'DNSKEY',
        ResourceQTypes::DS =>     'DS',
        ResourceQTypes::HINFO =>  'HINFO',
        ResourceQTypes::KEY =>    'KEY',
        ResourceQTypes::KX =>     'KX',
        ResourceQTypes::ISDN =>   'ISDN',
        ResourceQTypes::LOC =>    'LOC',
        ResourceQTypes::MB =>     'MB',
        ResourceQTypes::MD =>     'MD',
        ResourceQTypes::MF =>     'MF',
        ResourceQTypes::MG =>     'MG',
        ResourceQTypes::MINFO =>  'MINFO',
        ResourceQTypes::MR =>     'MR',
        ResourceQTypes::MX =>     'MX',
        ResourceQTypes::NAPTR =>  'NAPTR',
        ResourceQTypes::NS =>     'NS',
        ResourceQTypes::NULL =>   'NULL',
        ResourceQTypes::PTR =>    'PTR',
        ResourceQTypes::RP =>     'RP',
        ResourceQTypes::RT =>     'RT',
        ResourceQTypes::SIG =>    'SIG',
        ResourceQTypes::SOA =>    'SOA',
        ResourceQTypes::SPF =>    'SPF',
        ResourceQTypes::SRV =>    'SRV',
        ResourceQTypes::TXT =>    'TXT',
        ResourceQTypes::WKS =>    'WKS',
        ResourceQTypes::X25 =>    'X25'
    ];
    
    /**
     * @var LibDNS\Records\Types\DomainName
     */
    private $name;
    
    /**
     * @var int
     */
    private $type;
    
    /**
     * @return  string[int]
     */
    public static function getTypes()
    {
        return self::$types;
    }
    
    /**
     * @param   string $name Domain name.
     * @param   string $type Query type, such as 'A', 'AAAA', 'MX', etc.
     *
     * @throws  Icicle\Dns\Query\Exception\InvalidTypeException If the given type is invalid.
     * @throws  UnexpectedValueException If the given name is not a valid domain name.
     */
    public function __construct($name, $type)
    {
        if (is_int($type)) {
            if (!array_key_exists($type, self::$types)) {
                throw new InvalidTypeException($type);
            }
            $this->type = $type;
        } else {
            $type = strtoupper($type);
            if (false === ($key = array_search($type, self::$types))) {
                throw new InvalidTypeException($type);
            }
            $this->type = $key;
        }
        
        $this->name = new DomainName($name);
    }
    
    /**
     * @return  LibDNS\Records\Types\DomainName
     */
    public function getDomain()
    {
        return $this->name;
    }
    
    /**
     * @return  string
     */
    public function getTypeName()
    {
        return self::$types[$this->type];
    }
    
    /**
     * @return  int
     */
    public function getType()
    {
        return $this->type;
    }
}
