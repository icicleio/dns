<?php
namespace Icicle\Dns\Connector;

use Icicle\Dns\Executor\ExecutorInterface;

interface ConnectorInterface extends \Icicle\Socket\Client\ConnectorInterface
{
    /**
     * @param   string $domain Domain name.
     * @param   int $port Port number.
     * @param   mixed[] $options
     * @param   int|float $timeout Time until a request fails
     * @param   int $retries Number of times to retry the request until failing.
     *
     * @return  Icicle\Promise\PromiseInterface
     *
     * @resolve Icicle\Socket\ClientInterface
     *
     * @reject  Icicle\Dns\Execption\FailureException If the server returns a non-zero response code.
     * @reject  Icicle\Dns\Execption\NotFoundException If the domain cannot be resolved.
     *
     * @see     Icicle\Socket\Client\Connector::connect() $options are the same as this method.
     */
    public function connect(
        $domain,
        $port,
        array $options = null,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        $retries = ExecutorInterface::DEFAULT_RETRIES
    );
}
