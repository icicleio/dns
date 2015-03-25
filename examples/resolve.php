#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop\Loop;

$coroutine = Coroutine::call(function ($query, $timeout = 1) {
    echo "Query: {$query}:\n";
    
    $resolver = new Resolver(new Executor('8.8.8.8'));
    
    $ips = (yield $resolver->resolve($query, $timeout));
    
    foreach ($ips as $ip) {
        echo "IP: {$ip}\n";
    }
}, 'www.icicle.io');

$coroutine->capture(function (Exception $e) {
    echo "Exception: {$e->getMessage()}\n";
});

Loop::run();
