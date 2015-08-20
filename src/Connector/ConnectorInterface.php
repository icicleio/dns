<?php
namespace Icicle\Dns\Connector;

interface ConnectorInterface extends \Icicle\Socket\Client\ConnectorInterface
{
    /**
     * @coroutine
     *
     * @param string $domain Domain name.
     * @param int $port Port number.
     * @param mixed[] $options
     *
     * @return \Generator
     *
     * @resolve resource
     *
     * @throws \Icicle\Socket\Exception\FailureException If connecting fails.
     *
     * @see \Icicle\Socket\Client\Connector::connect() $options are the same as this method.
     */
    public function connect($domain, $port, array $options = []);
}
