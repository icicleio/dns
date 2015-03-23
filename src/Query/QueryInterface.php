<?php
namespace Icicle\Dns\Query;

interface QueryInterface
{
    public function getDomain();
    
    public function getType();
    
    public function getTypeName();
}