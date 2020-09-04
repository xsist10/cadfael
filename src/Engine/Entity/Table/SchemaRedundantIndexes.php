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
class SchemaRedundantIndexes
{
    public Index $redundant_index;
    public Index $dominant_index;

    public int $subpart_exists;
    public string $sql_drop_index;

    /**
     * SchemaRedundantIndexes constructor.
     * @param Index $redundant_index
     * @param Index $dominant_index
     * @param int $subpart_exists
     * @param string $sql_drop_index
     */
    public function __construct(
        Index $redundant_index,
        Index $dominant_index,
        int $subpart_exists,
        string $sql_drop_index
    ) {
        $this->redundant_index = $redundant_index;
        $this->dominant_index = $dominant_index;
        $this->subpart_exists = $subpart_exists;
        $this->sql_drop_index = $sql_drop_index;
    }

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
     * @param Table $table
     * @param array<string> $schema This is a raw record from sys.schema_redundant_indexes
     * @return SchemaRedundantIndexes
     */
    public static function createFromSys(Table $table, array $schema): SchemaRedundantIndexes
    {
        $redundantIndex = new Index($schema['redundant_index_name']);
        $redundantIndex->setTable($table);
        $redundantIndex->setColumns(
            ...self::getFilteredColumns(
                $table,
                explode(',', $schema['redundant_index_columns'])
            )
        );
        $redundantIndex->setNonUnique((bool)$schema['redundant_index_non_unique']);

        $dominantIndex = new Index($schema['dominant_index_name']);
        $dominantIndex->setTable($table);
        $dominantIndex->setColumns(
            ...self::getFilteredColumns(
                $table,
                explode(',', $schema['dominant_index_columns'])
            )
        );
        $dominantIndex->setNonUnique((bool)$schema['dominant_index_non_unique']);

        return new SchemaRedundantIndexes(
            $redundantIndex,
            $dominantIndex,
            (int)$schema['subpart_exists'],
            $schema['sql_drop_index']
        );
    }
}
