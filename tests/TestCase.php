<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Dns;

use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\ResourceBuilderFactory;
use Symfony\Component\Yaml\Yaml;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Creates a callback that must be called $count times or the test will fail.
     *
     * @param int $count Number of times the callback should be called.
     *
     * @return callable|\PHPUnit_Framework_MockObject_MockObject Object that is callable and expects to be called the
     *     given number of times.
     */
    public function createCallback($count)
    {
        $mock = $this->getMock('Icicle\Tests\Dns\Stub\CallbackStub');

        $mock->expects($this->exactly($count))
            ->method('__invoke');

        return $mock;
    }

    /**
     * @return array Array of A record requests and responses.
     */
    public function getARecords()
    {
        return Yaml::parse(file_get_contents(__DIR__ . '/data/a.yml'));
    }

    /**
     * @return array Array of MX record requests and responses.
     */
    public function getMxRecords()
    {
        return Yaml::parse(file_get_contents(__DIR__ . '/data/mx.yml'));
    }

    /**
     * @return array Array of NS record requests and responses.
     */
    public function getNsRecords()
    {
        return Yaml::parse(file_get_contents(__DIR__ . '/data/ns.yml'));
    }

    /**
     * @return array Array of invalid record requests and responses.
     */
    public function getInvalid()
    {
        return Yaml::parse(file_get_contents(__DIR__ . '/data/invalid.yml'));
    }

    /**
     * @param array $answers
     * @param array $authority
     *
     * @return \LibDNS\Messages\Message
     */
    public function createMessage(array $answers = null, array $authority = null)
    {
        $message = (new MessageFactory())->create(MessageTypes::RESPONSE);

        $builder = (new ResourceBuilderFactory())->create();

        $records = $message->getAnswerRecords();

        if (!empty($answers)) {
            foreach ($answers as $answer) {
                $record = $builder->build($answer['type']);
                $record->getData()->getField(0)->setValue($answer['value']);
                $record->setTTL($answer['ttl']);
                $records->add($record);
            }
        }

        if (!empty($authority)) {
            $records = $message->getAuthorityRecords();

            foreach ($authority as $answer) {
                $record = $builder->build($answer['type']);
                $record->getData()->getField(0)->setValue($answer['value']);
                $record->setTTL($answer['ttl']);
                $records->add($record);
            }
        }

        return $message;
    }
}