<?php
namespace Icicle\Dns\Connector;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Resolver\ResolverInterface;
use Icicle\Socket\Client\Connector as ClientConnector;
use Icicle\Socket\Client\ConnectorInterface as ClientConnectorInterface;
use Icicle\Socket\Exception\ExceptionInterface as SocketException;
use Icicle\Socket\Exception\FailureException;

class Connector implements ConnectorInterface
{
    /**
     * @var \Icicle\Dns\Resolver\ResolverInterface
     */
    private $resolver;
    
    /**
     * @var \Icicle\Socket\Client\ConnectorInterface
     */
    private $connector;
    
    /**
     * @param   \Icicle\Dns\Resolver\ResolverInterface $resolver
     * @param   \Icicle\Socket\Client\ConnectorInterface $connector
     */
    public function __construct(ResolverInterface $resolver, ClientConnectorInterface $connector = null)
    {
        $this->resolver = $resolver;
        $this->connector = $connector ?: new ClientConnector();
    }
    
    /**
     * @inheritdoc
     */
    public function connect(
        $domain,
        $port,
        array $options = null,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        $retries = ExecutorInterface::DEFAULT_RETRIES
    ) {
        $default = ['name' => $domain];
        $options = is_array($options) ? array_merge($default, $options) : $default;

        return new Coroutine($this->run($domain, $port, $timeout, $retries, $options));
    }

    /**
     * @coroutine
     *
     * @param   string $domain
     * @param   int $port
     * @param   float|int $timeout
     * @param   int $retries
     * @param   mixed[] $options
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Socket\Client\ClientInterface
     *
     * @reject  \Icicle\Socket\Exception\FailureException
     */
    protected function run($domain, $port, $timeout, $retries, array $options)
    {
        $ips = (yield $this->resolver->resolve($domain, $timeout, $retries));

        foreach ($ips as $ip) {
            try {
                yield $this->connector->connect($ip, $port, $options);
                return;
            } catch (SocketException $exception) {
                // Ignore exception and try next IP address.
            }
        }

        throw new FailureException("Could not connect to {$domain}:{$port}.");
    }
}
