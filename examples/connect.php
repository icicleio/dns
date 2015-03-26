#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop\Loop;

$timer = Loop::periodic(0.01, function () { echo "Waiting...\n"; });

$coroutine = Coroutine::call(function ($domain, $port, $timeout = 1) {
    echo "Connecting to {$domain}...\n";
    
    $connector = new Connector(new Resolver(new Executor('8.8.8.8')));
    
    $client = (yield $connector->connect($domain, $port, ['name' => '*.google.com'], $timeout));
    
    echo "Enabling crypto...\n";
    
    yield $client->enableCrypto(STREAM_CRYPTO_METHOD_TLS_CLIENT);
    
    echo "Crypto enabled.\n";
    
    $request  = "GET / HTTP/1.1\r\n";
    $request .= "Host: {$domain}\r\n";
    $request .= "Connection: close\r\n";
    $request .= "\r\n";
    
    yield $client->write($request);
    
    while ($client->isReadable()) {
        echo (yield $client->read());
    }
    
    echo "\n";
}, 'google.com', 443);

$coroutine
    ->cleanup([$timer, 'cancel'])
    ->capture(function (Exception $e) {
        echo "Exception: {$e->getMessage()}\n";
    }
);

Loop::run();
