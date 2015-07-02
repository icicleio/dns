<?php
namespace Icicle\Dns\Exception;

class FailureException extends Exception
{
    public function __construct(\Exception $exception)
    {
        parent::__construct(sprintf('Processing error: %s', $exception->getMessage()), 0, $exception);
    }
}
