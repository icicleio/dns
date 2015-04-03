<?php
namespace Icicle\Dns\Query;

use Icicle\Dns\Exception\InvalidTypeException;
use LibDNS\Records\QuestionFactory;

class Query implements QueryInterface
{
    /**
     * @var \LibDNS\Records\Question
     */
    private $question;

    /**
     * @param   string $name Domain name.
     * @param   string|int $type Query type, such as 'A', 'AAAA', 'MX', etc. or integer (see ResourceQTypes constants)
     *
     * @throws  \Icicle\Dns\Exception\InvalidTypeException If the given type is invalid.
     * @throws  \UnexpectedValueException If the given name is not a valid domain name.
     *
     * @see     \LibDNS\Records\ResourceQTypes
     */
    public function __construct($name, $type)
    {
        if (!is_int($type)) {
            $type = strtoupper($type);
            // Error reporting suppressed since constant() emits an E_WARNING if constant not found.
            // Check for null === $value handles error.
            $value = @constant('\LibDNS\Records\ResourceQTypes::'.$type);
            if (null === $value) {
                throw new InvalidTypeException($type);
            }
            $type = $value;
        }

        $this->question = (new QuestionFactory())->create($type);
        $this->question->setName($name);
    }

    /**
     * @return  \LibDNS\Records\Question
     */
    public function getQuestion()
    {
        return $this->question;
    }
}
