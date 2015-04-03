<?php
namespace Icicle\Dns\Query;

interface QueryInterface
{
    /**
     * @return  \LibDNS\Records\Question
     */
    public function getQuestion();
}