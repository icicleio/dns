<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns\Resolver;

use Icicle\Dns;
use Icicle\Dns\Exception\InvalidArgumentError;
use Icicle\Dns\Executor\Executor;

class BasicResolver implements Resolver
{
    /**
     * @var \Icicle\Dns\Executor\Executor
     */
    private $executor;
    
    /**
     * @param \Icicle\Dns\Executor\Executor|null $executor
     */
    public function __construct(Executor $executor = null)
    {
        $this->executor = $executor ?: Dns\executor();
    }
    
    /**
     * {@inheritdoc}
     */
    public function resolve($domain, array $options = [])
    {
        $mode = isset($options['mode']) ? $options['mode'] : self::IPv4;

        if (!($mode === self::IPv4 || $mode === self::IPv6)) {
            throw new InvalidArgumentError('Invalid resolver mode.');
        }

        if (strtolower($domain) === 'localhost') {
            yield $mode === self::IPv4 ? ['127.0.0.1'] : ['::1'];
            return;
        }

        /** @var \LibDNS\Messages\Message $response */
        $response = (yield $this->executor->execute($domain, $mode, $options));

        $answers = $response->getAnswerRecords();

        $result = [];

        /** @var \LibDNS\Records\Resource $record */
        foreach ($answers as $record) {
            // Skip any CNAME or other records returned in result.
            if ($record->getType() === $mode) {
                $result[] = $record->getData()->getField(0)->getValue();
            }
        }

        yield $result;
    }
}