<?php
namespace Icicle\Dns\Connector;

use Icicle\Dns\Executor\ExecutorInterface;
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
     * @param \Icicle\Dns\Resolver\ResolverInterface $resolver
     * @param \Icicle\Socket\Client\ConnectorInterface $connector
     */
    public function __construct(ResolverInterface $resolver, ClientConnectorInterface $connector = null)
    {
        $this->resolver = $resolver;
        $this->connector = $connector ?: new ClientConnector();
    }
    
    /**
     * {@inheritdoc}
     */
    public function connect(
        $domain,
        $port,
        array $options = null,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        $retries = ExecutorInterface::DEFAULT_RETRIES
    ) {
        // Check if $domain is actually an IP address.
        if (preg_match(self::IP_REGEX, $domain)) {
            yield $this->connector->connect($domain, $port, $options);
            return;
        }

        $default = ['name' => $domain];
        $options = null === $options ? $default : array_merge($default, $options);

        $ips = (yield $this->resolver->resolve($domain, $timeout, $retries));

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

        throw new FailureException(sprintf('Could not connect to %s:%d.', $domain, $port));
    }
}
