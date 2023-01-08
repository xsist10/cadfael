<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Table;

use Cadfael\Engine\Entity\Index;

/**
 * Class UnusedIndex
 * @package Cadfael\Engine\Entity\Table
 * @codeCoverageIgnore
 */
class UnusedIndex
{
    public Index $index;

    /**
     * UnusedIndex constructor.
     * @param Index $index
     */
    public function __construct(Index $index)
    {
        $this->index = $index;
    }
}
