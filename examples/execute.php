#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\CachingExecutor;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Executor\MultiExecutor;
use Icicle\Loop\Loop;

if (3 > $argc) {
    throw new InvalidArgumentException('Too few arguments provided. Usage: {DomainName} {RecordType}');
}

$domain = $argv[1];
$type = $argv[2];

$coroutine = Coroutine::call(function ($query, $type, $timeout = ExecutorInterface::DEFAULT_TIMEOUT) {
    $executor = new MultiExecutor();
    $executor->add(new Executor('8.8.8.8'));
    $executor->add(new Executor('8.8.4.4'));

    echo "Query: {$query}:\n";

    /** @var \LibDNS\Messages\Message $response */
    $response = (yield $executor->execute($query, $type, $timeout));

    $answers = $response->getAnswerRecords();

    /** @var \LibDNS\Records\Resource $record */
    foreach ($answers as $record) {
        echo "Result: Type:{$record->getType()} TTL:{$record->getTTL()} {$record->getData()}\n";
    }

    $authority = $response->getAuthorityRecords();

    /** @var \LibDNS\Records\Resource $record */
    foreach ($authority as $record) {
        echo "Authority: Type:{$record->getType()} TTL:{$record->getTTL()} {$record->getData()}\n";
    }
}, $domain, $type);

$coroutine->capture(function (Exception $e) {
    $class = get_class($e);
    echo "Exception of type {$class}: {$e->getMessage()}\n";
});

Loop::run();
