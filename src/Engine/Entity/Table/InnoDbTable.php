<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Entity\Table;

class InnoDbTable
{
    public int $table_id;
    public int $space;
    public string $name;
    public int $flag;
    public int $n_cols;
    /**
     * @type Enum
     * @Enum Any, Compact or Redundant, Undo, Dynamic
     */
    public string $row_format;
    public int $zip_page_size;
    /**
     * @type Enum
     * @Enum General, System, Undo, Single
     */
    public string $space_type;
    public int $instant_cols;

    private function __construct()
    {
    }

    /**
     * @param array<string> $schema This is a raw record from information_schema.innodb_tables or
     * information_schema.innodb_sys_tables (depending on version of MySQL)
     * @return InnoDbTable
     */
    public static function createFromInformationSchema(array $schema): InnoDbTable
    {
        $informationSchema = new InnoDbTable();
        $informationSchema->table_id = (int)$schema['TABLE_ID'];
        $informationSchema->space = (int)$schema['SPACE'];
        $informationSchema->name = (string)$schema['NAME'];
        $informationSchema->flag = (int)$schema['FLAG'];
        $informationSchema->n_cols = (int)$schema['N_COLS'];
        $informationSchema->row_format = (string)$schema['ROW_FORMAT'];
        $informationSchema->zip_page_size = (int)$schema['ZIP_PAGE_SIZE'];
        $informationSchema->space_type = (string)$schema['SPACE_TYPE'];
        $informationSchema->instant_cols = (int)$schema['INSTANT_COLS'];
        return $informationSchema;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
