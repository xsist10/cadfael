<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Entity\MySQL;

use Cadfael\Engine\Entity\Table as BaseTable;
use Cadfael\Engine\Entity\MySQL\Table\InformationSchema;
use Cadfael\Engine\Entity\MySQL\Table\SchemaAutoIncrementColumn;
use Cadfael\Engine\Entity\MySQL\Table\SchemaRedundantIndexes;

class Table extends BaseTable
{
    public ?InformationSchema $information_schema = null;
    public ?SchemaAutoIncrementColumn $schema_auto_increment_column = null;
    /**
     * @var array<SchemaRedundantIndexes>
     */
    public array $schema_redundant_indexes = [];

    /**
     * @param array<string> $schema This is a raw record from information_schema.TABLE
     * @return Table
     */
    public static function createFromInformationSchema(array $schema): Table
    {
        $table = new Table($schema['TABLE_NAME']);
        $table->information_schema = InformationSchema::createFromInformationSchema($schema);

        return $table;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     */
    public function setSchemaAutoIncrementColumn(SchemaAutoIncrementColumn $schema_auto_increment_column): void
    {
        $this->schema_auto_increment_column = $schema_auto_increment_column;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     */
    public function setSchemaRedundantIndexes(SchemaRedundantIndexes ...$schema_redundant_indexes): void
    {
        $this->schema_redundant_indexes = $schema_redundant_indexes;
    }

    public function isVirtual(): bool
    {
        // If we don't have an information_schema, we'll have to guess
        if (empty($this->information_schema)) {
            return true;
        }

        return // The blackhole engine acts just like writing to /dev/null
            $this->information_schema->engine === 'BLACKHOLE'
            // System view tables are virtual
            || $this->information_schema->table_type === 'SYSTEM VIEW'
            // Views are virtual
            || $this->information_schema->table_type === 'VIEW';
    }
}
