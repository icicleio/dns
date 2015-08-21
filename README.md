# Asynchronous DNS for Icicle

This library is a component for [Icicle](https://github.com/icicleio/Icicle), providing an asynchronous DNS query executor, resolver, and client connector. An asynchronous DNS server is currently under development and will be added to this component in the future. Like other Icicle components, this library uses [Promises](https://github.com/icicleio/Icicle/wiki/Promises) and [Generators](http://www.php.net/manual/en/language.generators.overview.php) for asynchronous operations that may be used to build [Coroutines](//github.com/icicleio/Icicle/wiki/Coroutines) to make writing asynchronous code more like writing synchronous code.

[![Build Status](https://img.shields.io/travis/icicleio/dns/v1.x.svg?style=flat-square)](https://travis-ci.org/icicleio/dns)
[![Coverage Status](https://img.shields.io/coveralls/icicleio/dns/v1.x.svg?style=flat-square)](https://coveralls.io/r/icicleio/dns)
[![Semantic Version](https://img.shields.io/github/release/icicleio/dns.svg?style=flat-square)](http://semver.org)
[![Apache 2 License](https://img.shields.io/packagist/l/icicleio/dns.svg?style=flat-square)](LICENSE)
[![@icicleio on Twitter](https://img.shields.io/badge/twitter-%40icicleio-5189c7.svg?style=flat-square)](https://twitter.com/icicleio)

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
        "icicleio/dns": "^0.5"
    }
}
```

#### Example

The example below uses a resolver to asynchronously find the IP address for the domain `icicle.io`.

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop;

$resolver = new Resolver();

$generator = function ($domain) use ($resolver) {
    try {
        $ips = (yield $resolver->resolve($domain));
        
        foreach ($ips as $ip) {
            echo "IP: {$ip}\n";
        }
    } catch (Exception $exception) {
        echo "Error when executing query: {$exception->getMessage()}\n";
    }
};

$coroutine = new Coroutine($generator('icicle.io'));
$coroutine->done();

Loop\run();
```

## Documentation

- [Executors](#executors) - Executes a DNS query.
    - [Creating an Executor](#creating-an-executor)
    - [Using an Executor](#using-an-executor)
    - [MultiExecutor](#multiexecutor)
- [Resolver](#resolver) - Resolves the IP address for a domain name.
- [Connector](#connector) - Connects to a host and port.

Methods returning a `Generator` can be used to create a [Coroutine](https://github.com/icicleio/icicle/wiki/Coroutines) (e.g., `new Coroutine($executor->execute(...))`) or yielded within another Coroutine (use `yield from` in PHP 7 for better performance).

This library uses [LibDNS](//github.com/DaveRandom/LibDNS) to create and parse DNS messages. Unfortunately the documentation for this library is currently limited to DocComments in the source code. If only using resolvers and connectors in this library, there is no need to worry about how LibDNS works. Executors returns coroutines that are resolved with `LibDNS\Messages\Message` instances, representing the response from the DNS server. Using these objects is simple and will be described in the executor section below.

#### Function prototypes

Prototypes for object instance methods are described below using the following syntax:

```php
ClassOrInterfaceName::methodName(ArgumentType $arg): ReturnType
```

## Executors

Executors are the foundation of the DNS component, performing any DNS query and returning the full results of that query. Resolvers and connectors depend on executors to perform the DNS query required for their operation.

Each executor implements `Icicle\Dns\Executor\ExecutorInterface` that defines a single method, `execute()`.

```php
$executorInterface->execute(
    string $domain,
    string|int $type,
    array $options = []
): Generator
```

Option | Type | Description
:-- | :-- | :--
`timeout` | `float` | Timeout until query fails. Default is 2 seconds.
`retries` | `int` | Number of times to attempt the query before failing. Default is 5 times.

An executor will retry a query a number of times if it doesn't receive a response within `timeout` seconds. The number of times a query will be retried before failing is defined by `retries`, with `timeout` seconds elapsing between each query attempt.

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `LibDNS\Message\Message` | Query response. Usage described below.
Rejected | `Icicle\Dns\Exception\FailureException` | If sending the request or parsing the response fails.
Rejected | `\Icicle\Dns\Exception\MessageException` | If the server returns a non-zero response code or no response is received.

### Creating an Executor

The simplest executor is `Icicle\Dns\Executor\Executor`, created by providing the constructor with the IP address of a DNS server to use to perform queries. It is recommended to use a DNS server closest to you, such as the local router. If this is not possible, Google operates two DNS server that also can be used at `8.8.8.8` and `8.8.4.4`.

```php
use Icicle\Dns\Executor\Executor;

$executor = new Executor('8.8.8.8');
```

The `Icicle\Dns\Executor\Executor` constructor also accepts an instance of `Icicle\Socket\Client\Connector` as the second argument if custom behavior is desired when connecting to the name server. If no instance is given, one is automatically created.

### Using an Executor

Once created, an executor is used by calling the `execute()` method with the domain and type of DNS query to be performed. The type may be a case-insensitive string naming a record type (e.g., `'A'`, `'MX'`, `'NS'`, `'PTR'`, `'AAAA'`) or the integer value corresponding to a record type (`LibDNS\Records\ResourceQTypes` defines constants corresponding to a the integer value of a type). `execute()` returns a coroutine fulfilled with an instance of `LibDNS\Messages\Message` that represents the response from the name server. `LibDNS\Messages\Message` objects have several methods that will need to be used to fetch the data in the response.

- `getAnswerRecords()`: Returns an instance of `LibDNS\Records\RecordCollection`, a traversable collection of `LibDNS\Record\Resource` objects containing the response answer records.
- `getAuthorityRecords()`: Returns an instance of `LibDNS\Records\RecordCollection` containing the response authority records.
- `getAdditionalRecords()`: Returns an instance of `LibDNS\Records\RecordCollection` containing the response additional records.
- `getAuthorityRecords()`: Returns an instance of `LibDNS\Records\RecordCollection` containing the response authority records.
- `isAuthoritative()`: Determines if the response is authoritative for the records returned.

DNS records in the traversable `LibDNS\Records\RecordCollection` objects are represented as instances of `LibDNS\Records\Resource`. These objects have several methods to access the data associated with the record.

- `getType()`: Returns the record type as an `integer`.
- `getName()`: Gets the domain name associated with the record as a `string`.
- `getData()`: Returns an `LibDNS\Records\RData` instance representing the records data. This object may be cast to a `string` or each field can be accessed with the `LibDNS\Records\RData::getField(int $index)` method. The number of fields in a resource depends on the type of resource (e.g., `MX` records contain two fields, a priority and a host name).
- `getTTL()`: Gets the TTL (time-to-live) as an `integer`.

Below is an example of how an executor can be used to find the NS records for a domain.

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\Executor;
use Icicle\Loop;
use LibDNS\Messages\Message;

$executor = new Executor('8.8.8.8');

$coroutine = new Coroutine($executor->execute('google.com', 'NS'));

$coroutine->done(
    function (Message $message) {
        foreach ($message->getAnswerRecords() as $resource) {
            echo "TTL: {$resource->getTTL()} Value: {$resource->getData()}\n";
        }
    },
    function (Exception $exception) {
        echo "Query failed: {$exception->getMessage()}\n";
    }
);

Loop\run();
```

### MultiExecutor

The `Icicle\Dns\Executor\MultiExecutor` class can be used to combine multiple executors to send queries to several name servers so queries can be resolved even if some name servers stop responding.

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Executor\MultiExecutor;
use Icicle\Loop;
use LibDNS\Messages\Message;

$executor = new MultiExecutor();

$executor->add(new Executor('8.8.8.8'));
$executor->add(new Executor('8.8.4.4'));

// Executor will send query to 8.8.4.4 if 8.8.8.8 does not respond.
$coroutine = new Coroutine($executor->execute('google.com', 'MX'));

$coroutine->done(
    function (Message $message) {
        foreach ($message->getAnswerRecords() as $resource) {
            echo "TTL: {$resource->getTTL()} Value: {$resource->getData()}\n";
        }
    },
    function (Exception $exception) {
        echo "Query failed: {$exception->getMessage()}\n";
    }
);

Loop\run();
```

Queries using the above executor will automatically send requests to the second name server if the first does not respond. Subsequent queries are initially sent to the last server that successfully responded to a query.

## Resolver

A resolver finds the IP addresses for a given domain. `Icicle\Dns\Resolver\Resolver` implements `Icicle\Dns\Resolver\ResolverInterface`, which defines a single method, `resolve()`. A resolver is essentially a specialized executor that performs only `A` queries, fulfilling the coroutine returned from `resolve()` with an array of IP addresses (even if only one or zero IP addresses is found, the coroutine is still resolved with an array).

```php
$resolverInterface->resolve(
    string $domain,
    array $options = []
): Generator
```

Option | Type | Description
:-- | :-- | :--
`mode` | `int` | Resolution mode: IPv4 or IPv6. Use the constants `ResolverInterface::IPv4` or ``ResolverInterface::IPv6`.
`timeout` | `float` | Timeout until query fails. Default is 2 seconds.
`retries` | `int` | Number of times to attempt the query before failing. Default is 5 times.

Like executors, a resolver will retry a query `retries` times if the name server does not respond within `timeout` seconds.

The `Icicle\Resolver\Resolver` class is constructed by passing an `Icicle\Executor\ExecutorInterface` instance that is used to execute queries to resolve domains. If no executor is given, one will be created by default, using `8.8.8.8` and `8.8.4.4` as DNS servers for the executor.

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `array` | Array of resolved IP addresses. May be empty.
Rejected | `Icicle\Dns\Exception\FailureException` | If sending the request or parsing the response fails.
Rejected | `\Icicle\Dns\Exception\MessageException` | If the server returns a non-zero response code or no response is received.

##### Example

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop;

$resolver = new Resolver();

$coroutine = new Coroutine($resolver->resolve('google.com'));

$coroutine->done(
    function (array $ips) {
        foreach ($ips as $ip) {
            echo "IP: {$ip}\n";
        }
    },
    function (Exception $exception) {
        echo "Query failed: {$exception->getMessage()}\n";
    }
);

Loop\run();
```

## Connector

The connector component connects to a server by first resolving the hostname provided, then making the connection and resolving the returned coroutine with an instance of `Icicle\Socket\Client\ClientInterface`. `Icicle\Dns\Connector\Connector` implements `Icicle\Socket\Client\ConnectorInterface` and `Icicle\Dns\Connector\ConnectorInterface`, allowing it to be used anywhere a standard connector (`Icicle\Socket\Client\ConnectorInterface`) is required, or allowing components to require a resolving connector (`Icicle\Dns\Connector\ConnectorInterface`).

`Icicle\Dns\Connector\ConnectorInterface` defines a single method, `connect()` that should resolve a host name and connect to one of the resolved servers, resolving the coroutine with the connected client.

```php
$connectorInterface->connect(
    string $domain,
    int $port,
    array $options = [],
): Generator
```

`Icicle\Dns\Connector\Connector` will attempt to connect to one of the IP addresses found for a given host name. If the server at that IP is unresponsive, the connector will attempt to establish a connection to the next IP in the list until a server accepts the connection. Only if the connector is unable to connect to all of the IPs will it reject the coroutine returned from `connect()`. The constructor also optionally accepts an instance of `Icicle\Socket\Client\ConnectorInterface` if custom behavior is desired when connecting to the resolved host.

Option | Type | Description
:-- | :-- | :--
`mode` | `int` | Resolution mode: IPv4 or IPv6. Use the constants `ResolverInterface::IPv4` or ``ResolverInterface::IPv6`.
`timeout` | `float` | Timeout until query fails. Default is 2 seconds.
`retries` | `int` | Number of times to attempt the query before failing. Default is 5 times.

Additionally, all the [other options available](https://github.com/icicleio/socket#connect) to `Icicle\Socket\Client\Connector::connect()` may also be used.

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `Icicle\Socket\Client\ClientInterface` | Connected client.
Rejected | `Icicle\Socket\Exception\FailureException` | If resolving the IP or connecting fails.

##### Example

```php
use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop;
use Icicle\Socket\Client\ClientInterface;

$connector = new Connector();

$coroutine = new Coroutine($connector->connect('google.com', 80));

$coroutine->done(
    function (ClientInterface $client) {
        echo "IP: {$client->getRemoteAddress()}\n";
        echo "Port: {$client->getRemotePort()}\n";
    },
    function (Exception $exception) {
        echo "Connecting failed: {$exception->getMessage()}\n";
    }
);

Loop\run();
```
