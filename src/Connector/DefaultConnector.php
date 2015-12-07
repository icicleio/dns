<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns\Connector;

use Icicle\Awaitable\Exception\TimeoutException;
use Icicle\Dns;
use Icicle\Dns\Exception\Exception as DnsException;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Socket;
use Icicle\Socket\Connector\Connector as SocketConnector;
use Icicle\Socket\Exception\FailureException;

class DefaultConnector implements Connector
{
    const IP_REGEX = '/^(?:\d{1,3}\.){3}\d{1,3}$|^\[?[\dA-Fa-f:]+:[\dA-Fa-f]{1,4}\]?$/';

    /**
     * @var \Icicle\Dns\Resolver\Resolver
     */
    private $resolver;
    
    /**
     * @var \Icicle\Socket\Connector\Connector
     */
    private $connector;
    
    /**
     * @param \Icicle\Dns\Resolver\Resolver|null $resolver
     * @param \Icicle\Socket\Connector\Connector|null $connector
     */
    public function __construct(Resolver $resolver = null, SocketConnector $connector = null)
    {
        $this->resolver = $resolver ?: Dns\resolver();
        $this->connector = $connector ?: Socket\connector();
    }
    
    /**
     * {@inheritdoc}
     */
    public function connect(string $domain, int $port = null, array $options = []): \Generator
    {
        // Check if $domain is actually an IP address.
        if (preg_match(self::IP_REGEX, $domain)) {
            return yield from $this->connector->connect($domain, $port, $options);
        }

        $options = array_merge(['name' => $domain], $options);

        try {
            $ips = yield from $this->resolver->resolve($domain, $options);
        } catch (DnsException $exception) {
            throw new FailureException(sprintf('Could not resolve host %s.', $domain), 0, $exception);
        }

        if (empty($ips)) {
            throw new FailureException(sprintf('Host for %s not found.', $domain));
        }

        foreach ($ips as $ip) {
            try {
                return yield from $this->connector->connect($ip, $port, $options);
            } catch (TimeoutException $exception) {
                // Connection timed out, try next IP address.
            } catch (FailureException $exception) {
                // Connection failed, try next IP address.
            }
        }

        throw new FailureException(sprintf('Could not connect to %s:%d.', $domain, $port), 0, $exception);
    }
}
