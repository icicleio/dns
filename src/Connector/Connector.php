<?php
namespace Icicle\Dns\Connector;

use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Resolver\ResolverInterface;
use Icicle\Promise\Promise;
use Icicle\Socket\Client\Connector as ClientConnector;
use Icicle\Socket\Client\ConnectorInterface as ClientConnectorInterface;

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
        
        $this->connector = $connector;
        if (null === $this->connector) {
            $this->connector = new ClientConnector();
        }
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
        return $this->resolver->resolve($domain, $timeout, $retries)
            ->then(function (array $ips) use ($port, $options) {
                $count = count($ips);
                if (1 === $count) {
                    return $this->connector->connect($ips[0], $port, $options);
                }

                $current = 0;
                return Promise::retry(
                    function () use (&$current, $ips, $port, $options) {
                        return $this->connector->connect($ips[$current], $port, $options);
                    },
                    function (\Exception $exception) use (&$current, $count) {
                        return ++$current >= $count;
                    }
                );
            });
    }
}
