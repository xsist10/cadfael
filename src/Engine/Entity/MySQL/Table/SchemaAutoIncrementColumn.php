<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\MySQL\Table;

/**
 * Class SchemaAutoIncrementColumn
 * @package Cadfael\Engine\Entity\MySQL\Table
 * @codeCoverageIgnore
 *
 * DTO of a record from sys.schema_auto_increment_columns
 */
class SchemaAutoIncrementColumn
{
    public string $column_name;
    public string $data_type;
    public string $column_type;
    public bool $is_signed;
    public bool $is_unsigned;
    public float $max_value;
    public float $auto_increment;
    public float $auto_increment_ratio;

    public function __construct()
    {
    }

    /**
     * @param array<string> $schema This is a raw record from sys.schema_auto_increment_columns
     * @return SchemaAutoIncrementColumn
     */
    public static function createFromSys(array $schema): SchemaAutoIncrementColumn
    {
        $schemaAutoIncrementColumns = new SchemaAutoIncrementColumn();
        $schemaAutoIncrementColumns->column_name = $schema['column_name'];
        $schemaAutoIncrementColumns->data_type = $schema['data_type'];
        $schemaAutoIncrementColumns->column_type = $schema['column_type'];
        $schemaAutoIncrementColumns->is_signed = $schema['is_signed'] == '1';
        $schemaAutoIncrementColumns->is_unsigned = $schema['is_unsigned'] == '1';
        $schemaAutoIncrementColumns->max_value = (float)$schema['max_value'];
        $schemaAutoIncrementColumns->auto_increment = (float)$schema['auto_increment'];
        $schemaAutoIncrementColumns->auto_increment_ratio = (float)$schema['auto_increment_ratio'];

        return $schemaAutoIncrementColumns;
    }
}
