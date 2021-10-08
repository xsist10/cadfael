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
    public int $rows_selected;
    public string $select_latency;
    public int $rows_inserted;
    public string $insert_latency;
    public int $rows_updated;
    public string $update_latency;
    public int $rows_deleted;
    public string $delete_latency;

    protected function __construct()
    {
    }

    /**
     * @param array<string> $payload This is a raw record from sys.schema_index_statistics
     * @return SchemaIndexStatistics
     */
    public static function createFromSys(array $payload)
    {
        $statistics = new SchemaIndexStatistics();
        $statistics->rows_selected = (int)$payload["rows_selected"];
        $statistics->select_latency = $payload["select_latency"];
        $statistics->rows_inserted = (int)$payload["rows_inserted"];
        $statistics->insert_latency = $payload["insert_latency"];
        $statistics->rows_updated = (int)$payload["rows_updated"];
        $statistics->update_latency = $payload["update_latency"];
        $statistics->rows_deleted = (int)$payload["rows_deleted"];
        $statistics->delete_latency = $payload["delete_latency"];

        return $statistics;
    }
}
