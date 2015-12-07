<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns;

use Icicle\Dns\Connector\{Connector, DefaultConnector};
use Icicle\Dns\Executor\{BasicExecutor, Executor, MultiExecutor};
use Icicle\Dns\Resolver\{BasicResolver, Resolver};

if (!function_exists(__NAMESPACE__ . '\execute')) {
    /**
     * @coroutine
     *
     * Uses the global executor to execute a DNS query.
     *
     * @see \Icicle\Dns\Executor\ExecutorInterface::execute()
     *
     * @param string $name
     * @param string|int $type
     * @param array $options
     *
     * @return \Generator
     *
     * @resolve \LibDNS\Messages\Message Response message.
     */
    function execute(string $name, $type, array $options = []): \Generator
    {
        return executor()->execute($name, $type, $options);
    }

    /**
     * Accesses and sets the global executor instance.
     *
     * @param \Icicle\Dns\Executor\Executor|null $executor
     *
     * @return \Icicle\Dns\Executor\Executor
     */
    function executor(Executor $executor = null)
    {
        static $instance;

        if (null !== $executor) {
            $instance = $executor;
        } elseif (null === $instance) {
            $instance = new MultiExecutor();
            $instance->add(new BasicExecutor('8.8.8.8'));
            $instance->add(new BasicExecutor('8.8.4.4'));
        }

        return $instance;
    }

    /**
     * @coroutine
     *
     * Uses the global resolver to resolve the IP address of a domain name.
     *
     * @see \Icicle\Dns\Resolver\ResolverInterface::resolve()
     *
     * @param string $domain
     * @param array $options
     *
     * @return \Generator
     *
     * @resolve array Array of IP addresses.
     */
    function resolve(string $domain, array $options = []): \Generator
    {
        return resolver()->resolve($domain, $options);
    }

    /**
     * Accesses and sets the global resolver instance.
     *
     * @param \Icicle\Dns\Resolver\Resolver|null $resolver
     *
     * @return \Icicle\Dns\Resolver\Resolver
     */
    function resolver(Resolver $resolver = null)
    {
        static $instance;

        if (null !== $resolver) {
            $instance = $resolver;
        } elseif (null === $instance) {
            $instance = new BasicResolver();
        }

        return $instance;
    }

    /**
     * @coroutine
     *
     * Uses the global connector to connect to the domain on the given port.
     *
     * @see \Icicle\Dns\Connector\ConnectorInterface::connector()
     *
     * @param string $domain
     * @param int $port
     * @param array $options
     *
     * @return \Generator
     *
     * @resolve \Icicle\Socket\SocketInterface
     */
    function connect(string $domain, int $port, array $options = []): \Generator
    {
        return connector()->connect($domain, $port, $options);
    }

    /**
     * Accesses and sets the global connector instance.
     *
     * @param \Icicle\Dns\Connector\Connector|null $connector
     *
     * @return \Icicle\Dns\Connector\Connector
     */
    function connector(Connector $connector = null)
    {
        static $instance;

        if (null !== $connector) {
            $instance = $connector;
        } elseif (null === $instance) {
            $instance = new DefaultConnector();
        }

        return $instance;
    }
}
