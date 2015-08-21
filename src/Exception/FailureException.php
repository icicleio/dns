<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns\Exception;

class FailureException extends Exception
{
    public function __construct(\Exception $exception)
    {
        parent::__construct(sprintf('Processing error: %s', $exception->getMessage()), 0, $exception);
    }
}
