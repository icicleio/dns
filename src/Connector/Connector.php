<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns\Connector;

use Icicle\Dns\Exception\Exception as DnsException;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Dns\Resolver\ResolverInterface;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Socket\Client\Connector as ClientConnector;
use Icicle\Socket\Client\ConnectorInterface as ClientConnectorInterface;
use Icicle\Socket\Exception\FailureException;

class Connector implements ConnectorInterface
{
    const IP_REGEX = '/^(?:\d{1,3}\.){3}\d{1,3}$|^\[?[\dA-Fa-f:]+:[\dA-Fa-f]{1,4}\]?$/';

    /**
     * @var \Icicle\Dns\Resolver\ResolverInterface
     */
    private $resolver;
    
    /**
     * @var \Icicle\Socket\Client\ConnectorInterface
     */
    private $connector;
    
    /**
     * @param \Icicle\Dns\Resolver\ResolverInterface|null $resolver
     * @param \Icicle\Socket\Client\ConnectorInterface|null $connector
     */
    public function __construct(ResolverInterface $resolver = null, ClientConnectorInterface $connector = null)
    {
        $this->resolver = $resolver ?: new Resolver();
        $this->connector = $connector ?: new ClientConnector();
    }
    
    /**
     * {@inheritdoc}
     */
    public function connect($domain, $port, array $options = [])
    {
        // Check if $domain is actually an IP address.
        if (preg_match(self::IP_REGEX, $domain)) {
            yield $this->connector->connect($domain, $port, $options);
            return;
        }

        $options = array_merge(['name' => $domain], $options);

        try {
            $ips = (yield $this->resolver->resolve($domain, $options));
        } catch (DnsException $exception) {
            throw new FailureException(sprintf('Could not resolve host %s.', $domain), 0, $exception);
        }

        if (empty($ips)) {
            throw new FailureException(sprintf('Host for %s not found.', $domain));
        }

        foreach ($ips as $ip) {
            try {
                yield $this->connector->connect($ip, $port, $options);
                return;
            } catch (TimeoutException $exception) {
                // Connection timed out, try next IP address.
            } catch (FailureException $exception) {
                // Connection failed, try next IP address.
            }
        }

        throw new FailureException(sprintf('Could not connect to %s:%d.', $domain, $port), 0, $exception);
    }
}
