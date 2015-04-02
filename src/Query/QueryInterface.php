<?php
namespace Icicle\Dns\Query;

interface QueryInterface
{
    /**
     * @return  \LibDNS\Records\Types\DomainName
     */
    public function getDomain();

    /**
     * @return  int
     */
    public function getType();
}