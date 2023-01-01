<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\Table\AccessInformation;
use Cadfael\Engine\Entity\Table\InformationSchema;
use Cadfael\Engine\Entity\Table\InnoDbTable;
use Cadfael\Engine\Entity\Table\SchemaAutoIncrementColumn;
use Cadfael\Engine\Entity\Table\SchemaRedundantIndex;
use Cadfael\Engine\Entity\Table\SchemaUnusedIndex;
use Cadfael\Engine\Exception\InvalidColumn;

class Table implements Entity
{
    /**
     * @var string
     */
    protected string $name;
    /**
     * @var Schema
     */
    protected Schema $schema;
    /**
     * @var array<Column>
     */
    protected array $columns = [];
    /**
     * @var array<Index>
     */
    protected array $indexes = [];

    public ?InformationSchema $information_schema = null;
    public ?SchemaAutoIncrementColumn $schema_auto_increment_column = null;
    public ?AccessInformation $access_information = null;
    public ?InnoDbTable $innodb_table = null;
    /**
     * @var array<SchemaRedundantIndex>
     */
    public array $schema_redundant_indexes = [];
    /**
     * @var array<SchemaUnusedIndex>
     */
    public array $schema_unused_indexes = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return Schema
     */
    public function getSchema(): Schema
    {
        return $this->schema;
    }

    /**
     * @param Schema $schema
     */
    public function setSchema(Schema $schema): void
    {
        $this->schema = $schema;
    }

    /**
     * @param Column ...$columns
     */
    public function setColumns(Column ...$columns): void
    {
        array_walk($columns, function (Column $column) {
            $column->setTable($this);
        });
        $this->columns = $columns;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param $name
     * @return Column
     * @throws InvalidColumn
     */
    public function getColumn($name): Column
    {
        foreach ($this->getColumns() as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        throw new InvalidColumn("Invalid column specified: $name");
    }

    /**
     * @return array<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param Index ...$indexes
     */
    public function setIndexes(Index ...$indexes): void
    {
        array_walk($indexes, function (Index $index) {
            $index->setTable($this);
        });
        $this->indexes = $indexes;
    }

    /**
     * @return array<Index>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * @return array<Column>
     */
    public function getPrimaryKeys(): array
    {
        return array_filter($this->getColumns(), function ($column) {
            return $column->isPartOfPrimaryKey();
        });
    }

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
     * @param SchemaAutoIncrementColumn|null $schema_auto_increment_column
     */
    public function setSchemaAutoIncrementColumn(?SchemaAutoIncrementColumn $schema_auto_increment_column): void
    {
        $this->schema_auto_increment_column = $schema_auto_increment_column;
    }

    /**
     * @return SchemaAutoIncrementColumn|null
     * @throws \Cadfael\Engine\Exception\MissingInformationSchema
     */
    public function getSchemaAutoIncrementColumn(): ?SchemaAutoIncrementColumn
    {
        if (is_null($this->schema_auto_increment_column)) {
            $this->setSchemaAutoIncrementColumn(SchemaAutoIncrementColumn::createFromTable($this));
        }
        return $this->schema_auto_increment_column;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param SchemaRedundantIndex ...$schema_redundant_indexes
     */
    public function setSchemaRedundantIndexes(SchemaRedundantIndex ...$schema_redundant_indexes): void
    {
        $this->schema_redundant_indexes = $schema_redundant_indexes;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param SchemaUnusedIndex ...$schema_unused_indexes
     */
    public function setUnusedRedundantIndexes(SchemaUnusedIndex ...$schema_unused_indexes): void
    {
        $this->schema_unused_indexes = $schema_unused_indexes;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param AccessInformation $access_information
     */
    public function setAccessInformation(AccessInformation $access_information): void
    {
        $this->access_information = $access_information;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param InnoDbTable $innodb_table
     */
    public function setInnoDbTable(InnoDbTable $innodb_table): void
    {
        $this->innodb_table = $innodb_table;
    }

    public function getTableSpaceType(): ?string
    {
        return $this->innodb_table
            ? $this->innodb_table->space_type
            : null;
    }

    public function getTableSpace(): ?Tablespace
    {
        if (!$this->innodb_table) {
            return null;
        }

        // Our database holds all the tablespaces
        $database = $this->getSchema()->getDatabase();
        return $database->getTablespace($this->innodb_table->space);
    }

    public function getNumRows(): int
    {
        return $this->information_schema
            ? $this->information_schema->table_rows
            : 0;
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

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
