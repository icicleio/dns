#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop;

$timer = Loop\periodic(0.01, function () { echo "Waiting... " . microtime(true) . "\n"; });

$coroutine = Coroutine\create(function () {
    echo "Connecting to google.com...\n";
    
    $connector = new Connector(new Resolver(new Executor('8.8.8.8')));

    /** @var \Icicle\Socket\Client\ClientInterface $client */
    $client = (yield $connector->connect('google.com', 443, ['name' => '*.google.com']));
    
    echo "Enabling crypto...\n";
    
    yield $client->enableCrypto(STREAM_CRYPTO_METHOD_TLS_CLIENT);
    
    echo "Crypto enabled.\n";
    
    $request  = "GET / HTTP/1.1\r\n";
    $request .= "Host: google.com\r\n";
    $request .= "Connection: close\r\n";
    $request .= "\r\n";
    
    yield $client->write($request);
    
    while ($client->isReadable()) {
        echo (yield $client->read());
    }
    
    echo "\n";
});

$coroutine
    ->cleanup([$timer, 'cancel'])
    ->capture(function (Exception $e) {
        echo "Exception: {$e->getMessage()}\n";
    }
);

Loop\run();
