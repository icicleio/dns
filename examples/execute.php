#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Executor\MultiExecutor;
use Icicle\Loop;

if (3 > $argc) {
    throw new InvalidArgumentException('Too few arguments provided. Usage: {DomainName} {RecordType}');
}

$domain = $argv[1];
$type = $argv[2];

$coroutine = Coroutine\create(function ($query, $type, $timeout = ExecutorInterface::DEFAULT_TIMEOUT) {
    $executor = new MultiExecutor();
    $executor->add(new Executor('8.8.8.8'));
    $executor->add(new Executor('8.8.4.4'));

    printf("Query: %s\n", $query);

    /** @var \LibDNS\Messages\Message $response */
    $response = yield from $executor->execute($query, $type, ['timeout' => $timeout]);

    $answers = $response->getAnswerRecords();

    /** @var \LibDNS\Records\Resource $record */
    foreach ($answers as $record) {
        printf("Result: Type: %s TTL: %d %s\n", $record->getType(), $record->getTTL(), $record->getData());
    }

    $authority = $response->getAuthorityRecords();

    /** @var \LibDNS\Records\Resource $record */
    foreach ($authority as $record) {
        printf("Authority: Type: %s TTL: %d %s\n", $record->getType(), $record->getTTL(), $record->getData());
    }
}, $domain, $type);

$coroutine->capture(function (Exception $e) {
    printf("Exception of type %s: %s\n", get_class($e), $e->getMessage());
});

Loop\run();
