<?php
namespace Icicle\Dns\Exception;

class FailureException extends RuntimeException
{
    public function __construct(\Exception $exception)
    {
        parent::__construct("Processing error: {$exception->getMessage()}", $exception->getCode(), $exception);
    }
}
