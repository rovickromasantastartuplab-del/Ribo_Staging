<?php

namespace Ai\AiAgent\Ingest\Web\Exceptions;

use Exception;

class InvalidContentType extends Exception
{
    public function __construct(
        string $message = 'Invalid webpage content type',
    ) {
        parent::__construct($message);
    }
}
