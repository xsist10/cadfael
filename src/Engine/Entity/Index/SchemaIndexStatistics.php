<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Index;

/**
 * Class AccessInformation
 * @package Cadfael\Engine\Entity\Index
 * @codeCoverageIgnore
 */
class SchemaIndexStatistics
{
    protected function __construct(
        public int $rows_selected,
        public string $select_latency,
        public int $rows_inserted,
        public string $insert_latency,
        public int $rows_updated,
        public string $update_latency,
        public int $rows_deleted,
        public string $delete_latency
    ) {
    }

    /**
     * @param array<string> $payload This is a raw record from sys.schema_index_statistics
     * @return SchemaIndexStatistics
     */
    public static function createFromSys(array $payload)
    {
        return new SchemaIndexStatistics(
            (int)$payload["rows_selected"],
            $payload["select_latency"],
            (int)$payload["rows_inserted"],
            $payload["insert_latency"],
            (int)$payload["rows_updated"],
            $payload["update_latency"],
            (int)$payload["rows_deleted"],
            $payload["delete_latency"]
        );
    }

    public static function getQuery(): string
    {
        return <<<EOF
            SELECT * FROM sys.schema_index_statistics WHERE table_schema=:schema
EOF;
    }
}
