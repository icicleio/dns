#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop\Loop;

$coroutine = Coroutine::call(function ($query, $timeout = 10) {
    echo "Query: {$query}:\n";
    
    $resolver = new Resolver('8.8.8.8');
    
    $answers = (yield $resolver->resolve($query, $timeout));
    
    foreach ($answers as $record) {
        echo "{$record->getData()}\n";
    }
}, 'google.com');

$coroutine->capture(function (Exception $e) {
    echo "Exception: {$e->getMessage()}\n";
});

Loop::run();
