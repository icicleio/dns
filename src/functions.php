<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns;

use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Connector\ConnectorInterface;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Executor\MultiExecutor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Dns\Resolver\ResolverInterface;

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
    function execute($name, $type, array $options = [])
    {
        return executor()->execute($name, $type, $options);
    }

    /**
     * Accesses and sets the global executor instance.
     *
     * @param \Icicle\Dns\Executor\ExecutorInterface|null $executor
     *
     * @return \Icicle\Dns\Executor\ExecutorInterface
     */
    function executor(ExecutorInterface $executor = null)
    {
        static $instance;

        if (null !== $executor) {
            $instance = $executor;
        } elseif (null === $instance) {
            $instance = new MultiExecutor();
            $instance->add(new Executor('8.8.8.8'));
            $instance->add(new Executor('8.8.4.4'));
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
    function resolve($domain, array $options = [])
    {
        return resolver()->resolve($domain, $options);
    }

    /**
     * Accesses and sets the global resolver instance.
     *
     * @param \Icicle\Dns\Resolver\ResolverInterface|null $resolver
     *
     * @return \Icicle\Dns\Resolver\ResolverInterface
     */
    function resolver(ResolverInterface $resolver = null)
    {
        static $instance;

        if (null !== $resolver) {
            $instance = $resolver;
        } elseif (null === $instance) {
            $instance = new Resolver();
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
    function connect($domain, $port, array $options = [])
    {
        return connector()->connect($domain, $port, $options);
    }

    /**
     * Accesses and sets the global connector instance.
     *
     * @param \Icicle\Dns\Connector\ConnectorInterface|null $connector
     *
     * @return \Icicle\Dns\Connector\ConnectorInterface
     */
    function connector(ConnectorInterface $connector = null)
    {
        static $instance;

        if (null !== $connector) {
            $instance = $connector;
        } elseif (null === $instance) {
            $instance = new Connector();
        }

        return $instance;
    }
}
