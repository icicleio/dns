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
//    $executor = new MultiExecutor();
//    $executor->add(new Executor('8.8.8.8'));
//    $executor->add(new Executor('8.8.4.4'));

    $executor = new Executor('8.8.8.8');

    echo "Query: {$query}:\n";

    /** @var \LibDNS\Records\RecordCollection $answers */
    $answers = (yield $executor->execute(new Query($query, 'A'), $timeout));

    /** @var \LibDNS\Records\Resource $record */
    foreach ($answers as $record) {
        echo "Result: Type:{$record->getType()} TTL:{$record->getTTL()} {$record->getData()}\n";
    }
}, 'www.icicle.io');

$coroutine->capture(function (Exception $e) {
    echo "Exception: {$e->getMessage()}\n";
});

Loop::run();
