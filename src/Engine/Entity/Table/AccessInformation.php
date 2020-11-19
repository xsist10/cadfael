<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Table;

/**
 * Class AccessInformation
 * @package Cadfael\Engine\Entity\Table
 * @codeCoverageIgnore
 */
class AccessInformation
{
    public int $read_count;
    public int $write_count;

    public function __construct(int $read_count, int $write_count)
    {
        $this->read_count = $read_count;
        $this->write_count = $write_count;
    }
}
