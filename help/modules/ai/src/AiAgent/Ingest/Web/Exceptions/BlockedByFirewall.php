<?php

namespace Ai\AiAgent\Ingest\Web\Exceptions;

use Exception;

class BlockedByFirewall extends Exception
{
    public function __construct(string $message = 'Blocked by firewall')
    {
        parent::__construct($message);
    }
}
