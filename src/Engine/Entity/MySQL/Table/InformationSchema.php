<?php

namespace Cadfael\Engine\Entity\MySQL\Table;

/**
 * Class InformationSchema
 * @package Cadfael\Engine\Entity\MySQL\Table
 * @codeCoverageIgnore
 *
 * DTO of a record from information_schema.TABLE
 */
class InformationSchema
{
    public string $table_type;
    public string $engine;
    public string $version;
    public string $row_format;
    public int $table_rows;
    public int $avg_row_length;
    public int $data_length;
    public int $max_data_length;
    public int $data_free;
    public ?string $auto_increment;
    public string $create_time;
    public ?string $update_time;
    public ?string $check_time;
    public string $table_collation;
    public ?string $checksum;
    public string $create_options;
    public string $table_comment;

    public function __construct()
    {
    }

    /**
     * @param array<string> $schema This is a raw record from information_schema.TABLE
     * @return InformationSchema
     */
    public static function createFromInformationSchema(array $schema): InformationSchema
    {
        $informationSchema = new InformationSchema();
        $informationSchema->table_type = $schema['TABLE_TYPE'];
        $informationSchema->engine = $schema['ENGINE'];
        $informationSchema->version = $schema['VERSION'];
        $informationSchema->row_format = $schema['ROW_FORMAT'];
        $informationSchema->table_rows = (int)$schema['TABLE_ROWS'];
        $informationSchema->avg_row_length = (int)$schema['AVG_ROW_LENGTH'];
        $informationSchema->data_length = (int)$schema['DATA_LENGTH'];
        $informationSchema->max_data_length = (int)$schema['MAX_DATA_LENGTH'];
        $informationSchema->data_free = (int)$schema['DATA_FREE'];
        $informationSchema->auto_increment = $schema['AUTO_INCREMENT'];
        $informationSchema->create_time = $schema['CREATE_TIME'];
        $informationSchema->update_time = $schema['UPDATE_TIME'];
        $informationSchema->check_time = $schema['CHECK_TIME'];
        $informationSchema->table_collation = $schema['TABLE_COLLATION'];
        $informationSchema->checksum = $schema['CHECKSUM'];
        $informationSchema->create_options = $schema['CREATE_OPTIONS'];
        $informationSchema->table_comment = $schema['TABLE_COMMENT'];
        return $informationSchema;
    }
}
