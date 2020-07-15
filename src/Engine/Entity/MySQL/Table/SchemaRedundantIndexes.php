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

    public function __construct()
    {
    }

    /**
     * @param array<string> $schema This is a raw record from sys.schema_redundant_indexes
     * @return SchemaRedundantIndexes
     */
    public static function createFromSys(array $schema): SchemaRedundantIndexes
    {
        $schemaRedundantIndexes = new SchemaRedundantIndexes();
        $schemaRedundantIndexes->redundant_index_name = $schema['redundant_index_name'];
        $schemaRedundantIndexes->redundant_index_columns = explode(',', $schema['redundant_index_columns']);
        $schemaRedundantIndexes->redundant_index_non_unique = (int)$schema['redundant_index_non_unique'];
        $schemaRedundantIndexes->dominant_index_name = $schema['dominant_index_name'];
        $schemaRedundantIndexes->dominant_index_columns = explode(',', $schema['dominant_index_columns']);
        $schemaRedundantIndexes->dominant_index_non_unique = (int)$schema['dominant_index_non_unique'];
        $schemaRedundantIndexes->subpart_exists = (int)$schema['subpart_exists'];
        $schemaRedundantIndexes->sql_drop_index = $schema['sql_drop_index'];

        return $schemaRedundantIndexes;
    }
}
