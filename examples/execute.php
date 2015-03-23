#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\CachingExecutor;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Executor\MultiExecutor;
use Icicle\Dns\Query\Query;
use Icicle\Loop\Loop;

$coroutine = Coroutine::call(function ($query, $timeout = ExecutorInterface::DEFAULT_TIMEOUT) {
    $executor = new MultiExecutor();
    $executor->add(new Executor('127.0.0.1'));
    $executor->add(new Executor('8.8.8.8'));
    
    echo "Query: {$query}:\n";
    
    $answers = (yield $executor->execute(new Query($query, 'A'), $timeout));
    
    foreach ($answers as $record) {
        echo "Result: ({$record->getType()}) {$record->getTTL()} {$record->getData()}\n";
    }
}, 'www.google.com');

$coroutine->capture(function (Exception $e) {
    echo "Exception: {$e->getMessage()}\n";
});

Loop::run();
