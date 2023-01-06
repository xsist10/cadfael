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
    public function __construct(public int $read_count, public int $write_count)
    {
    }

    /**
     * @param array<string> $schema This is a query from performance_schema.table_io_waits_summary_by_table
     * @return AccessInformation
     */
    public static function createFromIOSummary(array $schema): AccessInformation
    {
        return new AccessInformation(
            (int)$schema['COUNT_READ'],
            (int)$schema['COUNT_WRITE']
        );
    }

    public static function getQuery(): string
    {
        return <<<EOF
            SELECT OBJECT_NAME, COUNT_READ, COUNT_WRITE
            FROM performance_schema.table_io_waits_summary_by_table
            WHERE OBJECT_SCHEMA=:schema
EOF;
    }
}
