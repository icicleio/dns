#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\Dns;
use Icicle\Loop;

$timer = Loop\periodic(0.01, function () { echo "Waiting... " . microtime(true) . "\n"; });

$coroutine = Coroutine\create(function () {
    echo "Connecting to google.com...\n";

    /** @var \Icicle\Socket\SocketInterface $socket */
    $socket = (yield Dns\connect('google.com', 443, ['name' => '*.google.com']));
    
    echo "Enabling crypto...\n";
    
    yield $socket->enableCrypto(STREAM_CRYPTO_METHOD_TLS_CLIENT);
    
    echo "Crypto enabled.\n";
    
    $request  = "GET / HTTP/1.1\r\n";
    $request .= "Host: google.com\r\n";
    $request .= "Connection: close\r\n";
    $request .= "\r\n";
    
    yield $socket->write($request);
    
    while ($socket->isReadable()) {
        echo (yield $socket->read());
    }
    
    echo "\nDone.\n";
});

$coroutine
    ->cleanup([$timer, 'stop'])
    ->capture(function (Exception $e) {
        echo "Exception: {$e->getMessage()}\n";
    }
);

Loop\run();
