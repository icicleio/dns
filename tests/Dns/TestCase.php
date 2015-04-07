<?php
namespace Icicle\Tests\Dns;

use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\ResourceBuilderFactory;
use Symfony\Component\Yaml\Yaml;

class TestCase extends \Icicle\Tests\TestCase
{
    /**
     * @return  array Array of A record requests and responses.
     */
    public function getARecords()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/a.yml'));
    }

    /**
     * @return  array Array of MX record requests and responses.
     */
    public function getMxRecords()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/mx.yml'));
    }

    /**
     * @return  array Array of NS record requests and responses.
     */
    public function getNsRecords()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/ns.yml'));
    }

    /**
     * @return  array Array of invalid record requests and responses.
     */
    public function getInvalid()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/invalid.yml'));
    }

    /**
     * @param   array $answers
     * @param   array $authority
     *
     * @return  \LibDNS\Messages\Message
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