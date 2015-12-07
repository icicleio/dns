<?php

/*
 * This file is part of the DNS package for Icicle, a library for writing asynchronous code in PHP.
 *
 * @copyright 2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Dns\Exception;

use Icicle\Exception\InvalidArgumentError;

class InvalidTypeError extends InvalidArgumentError implements Error
{
    /**
     * @var int|string
     */
    private $type;

    /**
     * @param int|string $type
     */
    public function __construct($type)
    {
        if (is_int($type)) {
            $message = sprintf('%d does not correspond to a valid record type (must be between 0 and 65535).', $type);
        } else {
            $message = sprintf('"%s" does not name a valid record type.', $type);
        }
        
        parent::__construct($message);
        
        $this->type = $type;
    }

    /**
     * @return int|string
     */
    public function getType()
    {
        return $this->type;
    }
}
