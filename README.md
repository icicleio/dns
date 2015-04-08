# Asynchronous DNS for Icicle

This library is a component for [Icicle](//github.com/icicleio/Icicle), providing an asynchronous DNS query executor, resolver, and client connector. An asynchronous DNS server is currently under development and will be added to this component in the future. Like other Icicle components, this library returns [promises](//github.com/icicleio/Icicle/tree/master/src/Promise) from asynchronous operations that may be used to build [coroutines](//github.com/icicleio/Icicle/tree/master/src/Coroutine) to make writing asynchronous code more like writing synchronous code.

[![@icicleio on Twitter](https://img.shields.io/badge/twitter-%40icicleio-5189c7.svg?style=flat-square)](https://twitter.com/icicleio)
[![Build Status](https://img.shields.io/travis/icicleio/Dns/master.svg?style=flat-square)](https://travis-ci.org/icicleio/ReactAdaptor)
[![Coverage Status](https://img.shields.io/coveralls/icicleio/Dns.svg?style=flat-square)](https://coveralls.io/r/icicleio/ReactAdaptor)
[![Semantic Version](https://img.shields.io/badge/semver-v0.1.0-yellow.svg?style=flat-square)](http://semver.org)
[![Apache 2 License](https://img.shields.io/packagist/l/icicleio/dns.svg?style=flat-square)](LICENSE)

[![Join the chat at https://gitter.im/icicleio/Icicle](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/icicleio/Icicle)

##### Requirements

- PHP 5.5+

##### Installation

The recommended way to install is with the [Composer](http://getcomposer.org/) package manager. (See the [Composer installation guide](https://getcomposer.org/doc/00-intro.md) for information on installing and using Composer.)

Run the following command to use this library in your project: 

```bash
composer require icicleio/dns
```

You can also manually edit `composer.json` to add this library as a project requirement.

```js
// composer.json
{
    "require": {
        "icicleio/dns": "0.1.*"
    }
}
```

#### Example

```php
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop\Loop;

$resolver = new Resolver(new Executor('8.8.8.8'));

$promise = $resolver->resolve('icicle.io');

$promise->then(
    function (array $ips) {
        foreach ($ips as $ip) {
            echo "IP: {$ip}\n";
        }
    },
    function (Exception $exception) {
        echo "Error when executing query: {$exception->getMessage()}\n";
    }
);

Loop::run();
```

## Documentation

- [Executors](#executors)
    - [Creating an Executor](#creating-an-executor)
    - [Using an Executor](#using-an-executor)
    - [MultiExecutor](#multiexecutor)
- [Resolver](#resolver)
- [Connector](#connector)

All references to `PromiseInterface` in the documentation below are to `Icicle\Promise\PromiseInterface`, part of the promises component of [Icicle](//github.com/icicleio/Icicle). For more information on promises, see the [Promise API documentation](//github.com/icicleio/Icicle/tree/master/src/Promise) for more information.

This library uses [LibDNS](//github.com/DaveRandom/LibDNS) to create and parse DNS messages. Unfortunately the documentation for this library is currently limited to DocComments in the source code. If using only the resolver and connector components of this library, there is no need to worry about how this library works. The executor component returns promises that are resolved with `LibDNS\Messages\Message` instances, representing the response from the DNS server. Using these objects is simple and will be described in the executor section below.

#### Function prototypes

Prototypes for object instance methods are described below using the following syntax:

```php
ReturnType $classOrInterfaceName->methodName(ArgumentType $arg1, ArgumentType $arg2)
```

## Executors

Executors are the foundation of the DNS component, performing any DNS query and returning the full results of that query. Resolvers and connectors depend on executors to perform the DNS query required for their operation.

Each executor implements `Icicle\Dns\Executor\ExecutorInterface` that defines a single method, `execute()`.

```php
PromiseInterface $executorInterface->execute(
    string $domain,
    string|int $type,
    float|int $timeout = 2,
    int $retries = 5
)
```

An executor will retry a query a number of times if it doesn't receive a response within `$timeout` seconds. The number of times a query will be retried before failing is defined by `$retries`. `$timeout` seconds is allowed to elapse between each query attempt.

### Creating an Executor

The simplest executor is `Icicle\Dns\Executor\Executor`, created by providing the constructor with the IP address of a DNS server to use to perform queries. It is recommended to use a DNS server closest to you, such as the local router. If this is not possible, Google operates two DNS server that also can be used at `8.8.8.8` and `8.8.4.4`.

```php
use Icicle\Dns\Executor\Executor;

$executor = new Executor('8.8.8.8');
```

The `Icicle\Dns\Executor\Executor` constructor also accepts an instance of `Icicle\Socket\Client\Connector` as the second argument if custom behavior is desired when connecting to the name server. If no instance is given, one is automatically created.

### Using an Executor

Once created, an executor is used by calling the `execute()` method with the domain and type of DNS query to be performed. The type may be a case-insensitive string naming a record type (e.g., `'A'`, `'MX'`, `'NS'`, `'PTR'`, `'AAAA'`) or the integer value corresponding to a record type (`LibDNS\Records\ResourceQTypes` defines constants corresponding to these types). The `execute()` returns a promise fulfilled with an instance of `LibDNS\Messages\Message` representing the response from the DNS server. `LibDNS\Messages\Message` objects have several methods that will need to be used to fetch the data in the response.

- `getAnswerRecords()`: Returns an instance of `LibDNS\Records\RecordCollection`, a traversable collection of `LibDNS\Record\Resource` objects containing the response answer records.
- `getAuthorityRecords()`: Returns an instance of `LibDNS\Records\RecordCollection` containing the response authority records.
- `getAdditionalRecords()`: Returns an instance of `LibDNS\Records\RecordCollection` containing the response additional records.
- `getAuthorityRecords()`: Returns an instance of `LibDNS\Records\RecordCollection` containing the response authority records.
- `isAuthorative()`: Determines if the response is authoritative for the records returned.

DNS records in the traversable `LibDNS\Records\RecordCollection` objects are represented as instances of `LibDNS\Records\Resource`.

- `getType()`: Returns the record type as an integer.
- `getName()`: Gets the domain name associated with the record.
- `getData()`: Returns an `LibDNS\Records\RData` instance representing the records data. Casting the returned object to a string will return the data in the record as a string.
- `getTTL()`: Gets the TTL (time-to-live) as an integer.

Below is an example of how an executor can be used to find the NS records for a domain.

```php
use Icicle\Dns\Executor\Executor;
use Icicle\Loop\Loop;
use LibDNS\Messages\Message;

$executor = new Executor('8.8.8.8');

$promise = $executor->execute('google.com', 'NS');

$promise->then(
    function (Message $message) {
        foreach ($message->getAnswerRecords() as $resource) {
            echo "TTL: {$resource->getTTL()} Value: {$resource->getData()}\n";
        }
    },
    function (Exception $exception) {
        echo "Query failed: {$exception->getMessage()}\n";
    }
);

Loop::run();
```

### MultiExecutor

The `Icicle\Dns\Executor\MultiExecutor` class can be used to combine multiple executors to send queries to several name servers so queries can be resolved even if some name servers stop responding.

```php
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Executor\MultiExecutor;
use Icicle\Loop\Loop;
use LibDNS\Messages\Message;

$executor = new MultiExecutor();

$executor->add(new Executor('8.8.8.8'));
$executor->add(new Executor('8.8.4.4'));

// Executor will send query to 8.8.4.4 if 8.8.8.8 does not respond.
$promise = $executor->execute('google.com', 'MX');

$promise->then(
    function (Message $message) {
        foreach ($message->getAnswerRecords() as $resource) {
            echo "TTL: {$resource->getTTL()} Value: {$resource->getData()}\n";
        }
    },
    function (Exception $exception) {
        echo "Query failed: {$exception->getMessage()}\n";
    }
);

Loop::run();
```

Queries using the above executor will automatically send requests to the second name server if the first does not respond. Subsequent queries are initially sent to the last server that successfully responded to a query.

## Resolver

A resolver finds the IP addresses for a given domain. `Icicle\Dns\Resolver\Resolver` implements `Icicle\Dns\Resolver\ResolverInterface`, which defines a single method, `resolve()`. A resolver is essentially a specialized executor that performs only `A` queries, fulfilling the promise returned from `resolve()` with an array of IP addresses (even if only one IP address is found, the promise is still resolved with an array).

```php
PromiseInterface $resolverInterface->resolve(string $domain, float|int $timeout = 2, int $retries = 5)
```

Like executors, a resolver will retry a query `$retries` times if the name server does not respond within `$timeout` seconds.

The `Icicle\Resolver\Resolver` class is constructed by passing an `Icicle\Executor\ExecutorInterface` instance that is used to execute queries to resolve domains.

```php
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop\Loop;

$resolver = new Resolver(new Executor('8.8.8.8'));

$promise = $resolver->resolve('google.com');

$promise->then(
    function (array $ips) {
        foreach ($ips as $ip) {
            echo "IP: {$ip}\n";
        }
    },
    function (Exception $exception) {
        echo "Query failed: {$exception->getMessage()}\n";
    }
);

Loop::run();
```

## Connector

The connector component connects to a server by first resolving the hostname provided, then making the connection and resolving the returned promise with an instance of `Icicle\Socket\Client\ClientInterface`. `Icicle\Dns\Connector\Connector` implements `Icicle\Socket\Client\ConnectorInterface` and `Icicle\Dns\Connector\ConnectorInterface`, allowing it to be used anywhere a standard connector (`Icicle\Socket\Client\ConnectorInterface`) is required, or allowing components to require a resolving (`Icicle\Dns\Connector\ConnectorInterface`) connector.

`Icicle\Dns\Connector\ConnectorInterface` defines a single method, `connect()` that should resolve a host name and connect to one of the resolved servers, resolving the returned promise with the connected client.

```php
PromiseInterface $connectorInterface->connect(
    string $domain,
    int $port,
    array $options = null,
    float|int $timeout = 2,
    int $retries = 5)
```

`Icicle\Dns\Connector\Connector` will attempt to connect to one of the IP addresses found for a given host name. If the server at that IP is unresponsive, the connector will attempt to establish a connection to the next IP in the list until a server accepts the connection. Only if the connector is unable to connect to all of the IPs will it reject the promise returned from `connect()`. The constructor also optionally accepts an instance of `Icicle\Socket\Client\ConnectorInterface` if custom behavior is desired when connecting to the resolved host.

```php
use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop\Loop;
use Icicle\Socket\Client\ClientInterface;

$connector = new Connector(new Resolver(new Executor('8.8.8.8')));

$promise = $connector->connect('google.com', 80);

$promise->then(
    function (ClientInterface $client) {
        echo "IP: {$client->getRemoteAddress()}\n";
        echo "Port: {$client->getRemotePort()}\n";
    },
    function (Exception $exception) {
        echo "Connecting failed: {$exception->getMessage()}\n";
    }
);

Loop::run();
```
