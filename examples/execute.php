#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Query\Query;
use Icicle\Loop\Loop;

$coroutine = Coroutine::call(function ($query, $timeout = 10) {
    echo "Query: {$query}:\n";
    
    $executor = new Executor('8.8.8.8');
    
    $answers = (yield $executor->execute(new Query($query, 'A'), $timeout));
    
    foreach ($answers as $record) {
        echo "Result: ({$record->getType()}) {$record->getData()}\n";
    }
}, 'www.icicle.io');

$coroutine->capture(function (Exception $e) {
    echo "Exception: {$e->getMessage()}\n";
});

Loop::run();
