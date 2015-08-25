#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop;

if (2 > $argc) {
    throw new InvalidArgumentException('Too few arguments provided. Usage: {DomainName}');
}

$domain = $argv[1];

$coroutine = Coroutine\create(function ($query, $timeout = 1) {
    printf("Query: %s\n", $query);
    
    $resolver = new Resolver();
    
    $ips = yield from $resolver->resolve($query, ['timeout' => $timeout]);
    
    foreach ($ips as $ip) {
        printf("IP: %s\n", $ip);
    }
}, $domain);

$coroutine->capture(function (Exception $e) {
    printf("Exception: %s\n", $e->getMessage());
});

Loop\run();
