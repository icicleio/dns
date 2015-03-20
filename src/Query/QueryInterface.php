<?php
namespace Icicle\Dns\Query;

interface QueryInterface
{
    public function getName();
    
    public function getType();
    
    public function getTypeName();
}