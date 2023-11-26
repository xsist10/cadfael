<?php

declare(strict_types=1);

namespace Cadfael;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

trait NullLoggerDefault
{
    public function log(): LoggerInterface
    {
        if (!$this->logger) {
            return new NullLogger();
        }
        return $this->logger;
    }
}
