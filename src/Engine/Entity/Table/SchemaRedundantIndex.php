<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Table;

use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Table;

/**
 * Class SchemaRedundantIndexes
 * @package Cadfael\Engine\Entity\Table
 * @codeCoverageIgnore
 *
 * DTO of a record from sys.schema_redundant_indexes
 */
class SchemaRedundantIndex
{
    /**
     * SchemaRedundantIndexes constructor.
     * @param Index $redundant_index
     * @param Index $dominant_index
     * @param int $subpart_exists
     * @param string $sql_drop_index
     */
    public function __construct(
        public Index $redundant_index,
        public Index $dominant_index,
        public int $subpart_exists,
        public string $sql_drop_index
    )
    {}

    /**
     * @param Table $table
     * @param array<string> $columns
     * @return array<Column>
     */
    private static function getFilteredColumns(Table $table, array $columns)
    {
        return array_filter(
            $table->getColumns(),
            function (Column $column) use ($columns) : bool {
                return in_array($column->getName(), $columns);
            }
        );
    }

    /**
     * @param array<Index> $indexes Hash of indexes (key is index name, value is Index object)
     * @param array<string> $schema This is a raw record from sys.schema_redundant_indexes
     * @return SchemaRedundantIndex
     */
    public static function createFromSys(array $indexes, array $schema): SchemaRedundantIndex
    {
        $redundantIndex = $indexes[$schema['redundant_index_name']];
        $redundantIndex->setUnique(!(bool)$schema['redundant_index_non_unique']);

        $dominantIndex = $indexes[$schema['dominant_index_name']];
        $dominantIndex->setUnique(!(bool)$schema['dominant_index_non_unique']);

        return new SchemaRedundantIndex(
            $redundantIndex,
            $dominantIndex,
            (int)$schema['subpart_exists'],
            $schema['sql_drop_index']
        );
    }

    public static function getQuery(): string
    {
        return <<<EOF
            SELECT * FROM sys.schema_redundant_indexes WHERE table_schema=:schema
EOF;
    }
}
