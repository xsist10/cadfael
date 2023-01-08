<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Table;

use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Exception\InvalidColumnType;
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
    protected function __construct(
        public string $column_name,
        public string $data_type,
        public string $column_type,
        public bool $is_signed,
        public bool $is_unsigned,
        public float $max_value,
        public int $auto_increment,
        public float $auto_increment_ratio
    ) {
    }

    /**
     * @param array<string> $schema This is a raw record from sys.schema_auto_increment_columns
     * @return SchemaAutoIncrementColumn
     */
    public static function createFromSys(array $schema): SchemaAutoIncrementColumn
    {
        return new SchemaAutoIncrementColumn(
            $schema['column_name'],
            $schema['data_type'],
            $schema['column_type'],
            $schema['is_signed'] == '1',
            $schema['is_unsigned'] == '1',
            (float)$schema['max_value'],
            (int)$schema['auto_increment'],
            (float)$schema['auto_increment_ratio']
        );
    }

    public static function getQuery(): string
    {
        return <<<EOF
            SELECT * FROM sys.schema_auto_increment_columns WHERE table_schema=:schema
EOF;
    }

    /**
     * Attempt to automatically determine the SchemaAutoIncrementColumn using existing meta data
     *
     * @param Table $table
     * @return ?SchemaAutoIncrementColumn
     * @throws MissingInformationSchema
     * @throws InvalidColumnType
     */
    public static function createFromTable(Table $table): ?SchemaAutoIncrementColumn
    {
        if (is_null($table->information_schema)) {
            throw new MissingInformationSchema();
        }
        foreach ($table->getColumns() as $column) {
            if ($column->isAutoIncrementing()) {
                $ratio = ($table->information_schema->auto_increment - 1) / $column->getCapacity();

                return new SchemaAutoIncrementColumn(
                    $column->getName(),
                    $column->information_schema->data_type,
                    $column->information_schema->column_type,
                    $column->isSigned(),
                    !$column->isSigned(),
                    $column->getCapacity(),
                    $table->information_schema->auto_increment,
                    $ratio
                );
            }
        }

        return null;
    }
}
