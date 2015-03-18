<?php
namespace Icicle\Dns\Resolver\Exception;

class NotFoundException extends RuntimeException
{
    private $domain;
    
    public function __construct($domain)
    {
        parent::__construct("Could not resolve {$domain}.");
        
        $this->domain = $domain;
    }
    
    public function getDomain()
    {
        return $this->domain;
    }
}
