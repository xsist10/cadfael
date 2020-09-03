<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\MySQL\Table;

/**
 * Class SchemaRedundantIndexes
 * @package Cadfael\Engine\Entity\MySQL\Table
 * @codeCoverageIgnore
 *
 * DTO of a record from sys.schema_redundant_indexes
 */
class SchemaRedundantIndexes
{
    public string $redundant_index_name;
    /**
     * @todo Updated to support Column instance
     * @var  array<string>
     */
    public array $redundant_index_columns;
    public int $redundant_index_non_unique;
    public string $dominant_index_name;
    /**
     * @todo Updated to support Column instance
     * @var  array<string>
     */
    public array $dominant_index_columns;
    public int $dominant_index_non_unique;
    public int $subpart_exists;
    public string $sql_drop_index;

    /**
     * SchemaRedundantIndexes constructor.
     * @param string $redundant_index_name
     * @param array<string> $redundant_index_columns
     * @param int $redundant_index_non_unique
     * @param string $dominant_index_name
     * @param array<string> $dominant_index_columns
     * @param int $dominant_index_non_unique
     * @param int $subpart_exists
     * @param string $sql_drop_index
     */
    public function __construct(
        string $redundant_index_name,
        array $redundant_index_columns,
        int $redundant_index_non_unique,
        string $dominant_index_name,
        array $dominant_index_columns,
        int $dominant_index_non_unique,
        int $subpart_exists,
        string $sql_drop_index
    )
    {
        $this->redundant_index_name = $redundant_index_name;
        $this->redundant_index_columns = $redundant_index_columns;
        $this->redundant_index_non_unique = $redundant_index_non_unique;
        $this->dominant_index_name = $dominant_index_name;
        $this->dominant_index_columns = $dominant_index_columns;
        $this->dominant_index_non_unique = $dominant_index_non_unique;
        $this->subpart_exists = $subpart_exists;
        $this->sql_drop_index = $sql_drop_index;
    }

    /**
     * @param array<string> $schema This is a raw record from sys.schema_redundant_indexes
     * @return SchemaRedundantIndexes
     */
    public static function createFromSys(array $schema): SchemaRedundantIndexes
    {
        $schemaRedundantIndexes = new SchemaRedundantIndexes(
            $schema['redundant_index_name'],
            explode(',', $schema['redundant_index_columns']),
            (int)$schema['redundant_index_non_unique'],
            $schema['dominant_index_name'],
            explode(',', $schema['dominant_index_columns']),
            (int)$schema['dominant_index_non_unique'],
            (int)$schema['subpart_exists'],
            $schema['sql_drop_index']
        );

        return $schemaRedundantIndexes;
    }
}
