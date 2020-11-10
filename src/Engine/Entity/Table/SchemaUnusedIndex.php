<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Table;

use Cadfael\Engine\Entity\Index;

/**
 * Class SchemaUnusedIndex
 * @package Cadfael\Engine\Entity\Table
 * @codeCoverageIgnore
 *
 * DTO of a record from sys.schema_redundant_indexes
 */
class SchemaUnusedIndex
{
    public Index $index;

    /**
     * SchemaRedundantIndexes constructor.
     * @param Index $index
     */
    public function __construct(Index $index)
    {
        $this->index = $index;
    }
}
