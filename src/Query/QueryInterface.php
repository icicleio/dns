<?php
namespace Icicle\Dns\Query;

interface QueryInterface
{
    /**
     * @return  \LibDNS\Records\Types\DomainName
     */
    public function getDomain();

    /**
     * @return  string
     */
    public function getType();

    /**
     * @return  int
     */
    public function getTypeName();
}