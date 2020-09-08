<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Table;

use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Exception\MissingInformationSchema;

/**
 * Class SchemaAutoIncrementColumn
 * @package Cadfael\Engine\Entity\Table
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
    public int $auto_increment;
    public float $auto_increment_ratio;

    protected function __construct() {}

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
        $schemaAutoIncrementColumns->auto_increment = (int)$schema['auto_increment'];
        $schemaAutoIncrementColumns->auto_increment_ratio = (float)$schema['auto_increment_ratio'];

        return $schemaAutoIncrementColumns;
    }

    /**
     * Attempt to automatically determine the SchemaAutoIncrementColumn using existing meta data
     *
     * @param Table $table
     * @return ?SchemaAutoIncrementColumn
     * @throws MissingInformationSchema
     */
    public static function createFromTable(Table $table): ?SchemaAutoIncrementColumn
    {
        if (is_null($table->information_schema)) {
            throw new MissingInformationSchema();
        }
        foreach ($table->getColumns() as $column) {
            if ($column->isAutoIncrementing()) {
                $schemaAutoIncrementColumns = new SchemaAutoIncrementColumn();
                $schemaAutoIncrementColumns->column_name = $column->getName();
                $schemaAutoIncrementColumns->data_type = $column->information_schema->data_type;
                $schemaAutoIncrementColumns->column_type = $column->information_schema->column_type;
                $schemaAutoIncrementColumns->is_signed = $column->isSigned();
                $schemaAutoIncrementColumns->is_unsigned = !$column->isSigned();
                $schemaAutoIncrementColumns->max_value = $column->getCapacity();
                $schemaAutoIncrementColumns->auto_increment = (int)$table->information_schema->auto_increment;
                $ratio = ($table->information_schema->auto_increment - 1) / $column->getCapacity();
                $schemaAutoIncrementColumns->auto_increment_ratio = $ratio;

                return $schemaAutoIncrementColumns;
            }
        }

        return null;
    }
}
