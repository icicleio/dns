<?php
namespace Icicle\Dns\Connector;

use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Executor\ExecutorInterface;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Dns\Resolver\ResolverInterface;
use Icicle\Socket\Client;

class Connector implements ConnectorInterface
{
    /**
     * @var Icicle\Dns\Resolver\ResolverInterface
     */
    private $resolver;
    
    /**
     * @param   string $nameserver Nameserver IP address.
     *
     * @return  self
     */
    public static function create($nameserver)
    {
        return new static(
            new Resolver(
                new Executor($nameserver)
            )
        );
    }
    
    /**
     * @param   Icicle\Dns\Resolver\ResolverInterface
     */
    public function __construct(ResolverInterface $resolver)
    {
        $this->resolver = $resolver;
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
            ->then(function ($ip) use ($port, $options) {
                return Client::connect($ip, $port, $options);
            });
    }
}
