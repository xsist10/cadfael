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

    public static function getQuery(): string
    {
        return <<<EOF
            SELECT object_schema, object_name, index_name
            FROM performance_schema.table_io_waits_summary_by_index_usage
            WHERE index_name IS NOT NULL
                AND index_name != 'PRIMARY'
                AND count_star = 0
                AND object_schema = :schema
            ORDER BY object_schema, object_name;
EOF;
    }
}
