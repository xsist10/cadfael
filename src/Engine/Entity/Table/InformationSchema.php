<?php

namespace Cadfael\Engine\Entity\Table;

/**
 * Class InformationSchema
 * @package Cadfael\Engine\Entity\Table
 * @codeCoverageIgnore
 *
 * DTO of a record from information_schema.TABLE
 */
class InformationSchema
{
    protected function __construct(
        public string $table_type,
        public string $engine,
        public ?string $version,
        public string $row_format,
        public int $table_rows,
        public int $avg_row_length,
        public int $data_length,
        public int $max_data_length,
        public int $data_free,
        public int $auto_increment,
        public ?string $create_time,
        public ?string $update_time,
        public ?string $check_time,
        public string $table_collation,
        public ?string $checksum,
        public ?string $create_options,
        public string $table_comment
    ) {
    }

    /**
     * @param array<string> $schema This is a raw record from information_schema.TABLE
     * @return InformationSchema
     */
    public static function createFromInformationSchema(array $schema): InformationSchema
    {
        return new InformationSchema(
            $schema['TABLE_TYPE'] ?? 'BASE TABLE',
            $schema['ENGINE'] ?? 'InnoDB',
            $schema['VERSION'] ?? '10',
            $schema['ROW_FORMAT'] ?? 'Dynamic',
            $schema['TABLE_ROWS'] ?? 0,
            $schema['AVG_ROW_LENGTH'] ?? 0,
            $schema['DATA_LENGTH'] ?? 0,
            $schema['MAX_DATA_LENGTH'] ?? 0,
            $schema['DATA_FREE'] ?? 0,
            $schema['AUTO_INCREMENT'] ?? 0,
            $schema['CREATE_TIME'] ?? date('Y-m-d H:i:s'),
            $schema['UPDATE_TIME'] ?? null,
            $schema['CHECK_TIME'] ?? null,
            $schema['TABLE_COLLATION'] ?? 'utf8mb4_0900_ai_ci',
            $schema['CHECKSUM'] ?? null,
            $schema['CREATE_OPTIONS'] ?? '',
            $schema['TABLE_COMMENT'] ?? ''
        );
    }
}
