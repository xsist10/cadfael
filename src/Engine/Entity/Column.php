<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\Column\InformationSchema;
use Cadfael\Engine\Exception\InvalidColumnType;
use Cadfael\Engine\Exception\UnknownColumnType;

class Column implements Entity
{
    protected string $name;
    protected ?Table $table = null;
    public InformationSchema $information_schema;
    public int $cardinality = 0;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param array<string> $schema This is a raw record from information_schema.COLUMN
     * @return Column
     */
    public static function createFromInformationSchema(array $schema)
    {
        $column = new Column($schema['COLUMN_NAME']);
        $column->information_schema = InformationSchema::createFromInformationSchema($schema);

        return $column;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setTable(Table $table): void
    {
        $this->table = $table;
    }

    public function getTable(): ?Table
    {
        return $this->table;
    }

    public function __toString(): string
    {
        return (string)$this->table . "." . $this->name;
    }

    public function isPartOfPrimaryKey() : bool
    {
        return $this->information_schema->column_key === 'PRI';
    }

    public function isVirtual(): bool
    {
        // In MySQL, generated columns contain a generation expression in the information schema
        // COLUMN.generation_expression field.
        return !empty($this->information_schema->generation_expression);
    }

    public function isSigned(): bool
    {
        return $this->isNumeric()
            && strpos($this->information_schema->column_type, 'unsigned') === false;
    }

    public function isAutoIncrementing(): bool
    {
        return strpos($this->information_schema->extra, 'auto_increment') !== false;
    }

    public function isInteger(): bool
    {
        return in_array($this->information_schema->data_type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint' ]);
    }

    public function isNumeric(): bool
    {
        return $this->isInteger()
            || in_array($this->information_schema->data_type, [ 'bit', 'decimal', 'double', 'float' ]);
    }

    public function isString(): bool
    {
        $string_types = [ 'char', 'longtext', 'mediumtext', 'text', 'tinytext', 'varchar' ];
        return in_array($this->information_schema->data_type, $string_types);
    }

    public function isBinary(): bool
    {
        return in_array($this->information_schema->data_type, ['binary', 'varbinary']);
    }

    public function isPrefixAllowed(): bool
    {
        return in_array($this->information_schema->data_type, InformationSchema::PREFIX_ALLOWED_DATA_TYPES);
    }

    public function getStorageByteSize(): float
    {
        $size = [
            'tinytext' => [
                'body'   => 255,
                'header' => 1,
            ],
            'text' => [
                'body'   => 65535,
                'header' => 2,
            ],
            'blob' => [
                'body'   => 65535,
                'header' => 2,
            ],
            'binary' => [
                'body'   => $this->information_schema->character_maximum_length,
                'header' => 0,
            ],
            'bit' => [
                'body'   => ceil(($this->information_schema->numeric_precision + 7) / 8),
                'header' => 0,
            ],
            'varbinary' => [
                'body'   => $this->information_schema->character_maximum_length,
                'header' => 1,
            ],
            'mediumblob' => [
                'body'   => 16777215,
                'header' => 3,
            ],
            'mediumtext' => [
                'body'   => 16777215,
                'header' => 3,
            ],
            'longtext' => [
                'body'   => 4294967295,
                'header' => 4,
            ],
            'json' => [
                'body'   => 4294967295,
                'header' => 4,
            ],
            'longblob' => [
                'body'   => 4294967295,
                'header' => 4,
            ],
            'char' => [
                'body'   => $this->information_schema->character_maximum_length,
                'header' => 0
            ],
            'varchar' => [
                'body'   => $this->information_schema->character_maximum_length,
                'header' => ($this->information_schema->character_maximum_length > 255 ? 2 : 1)
            ],
            'tinyint' => [
                'body'   => 1,
                'header' => 0
            ],
            'smallint' => [
                'body'   => 2,
                'header' => 0
            ],
            'mediumint' => [
                'body'   => 3,
                'header' => 0
            ],
            'int' => [
                'body'   => 4,
                'header' => 0
            ],
            'bigint' => [
                'body'   => 8,
                'header' => 0
            ],
            'float' => [
                'body'   => 4,
                'header' => 0
            ],
            'double' => [
                'body'   => 8,
                'header' => 0
            ],
            'decimal' => [
                'body'   => $this->information_schema->numeric_precision,
                'header' => 2
            ],
            'date' => [
                'body'   => 3,
                'header' => 0
            ],
            'datetime' => [
                'body'   => 8,
                'header' => 0
            ],
            'timestamp' => [
                'body'   => 4,
                'header' => 0
            ],
            'time' => [
                'body'   => 3,
                'header' => 0
            ],
            'year' => [
                'body'   => 1,
                'header' => 0
            ],
            'enum' => [
                'body'   => 1,
                'header' => 0
            ],
            'set' => [
                'body'   => 1,
                'header' => 0
            ],
        ];

        if (!empty($size[$this->information_schema->data_type])) {
            return $size[$this->information_schema->data_type]['body']
                + $size[$this->information_schema->data_type]['header'];
        }

        throw new UnknownColumnType($this->information_schema->data_type . " is an unknown data type.");
    }

    public function getCapacity(): float
    {
        if (!$this->isInteger()) {
            throw new InvalidColumnType('Column is not an integer type.');
        }

        $fields = [
            'tinyint'   => 127,
            'smallint'  => 32767,
            'mediumint' => 8388607,
            'int'       => 2147483647,
            'bigint'    => 9223372036854775807,
        ];

        $capacity = $fields[$this->information_schema->data_type];

        // Deal with MySQL freaking out about unsigned bigint :P
        // It's possible that unsigned bigint isn't even allowed
        if (!$this->isSigned()) {
            $capacity = $capacity * 2 + 1;
        }

        return $capacity;
    }

    public function setCardinality(int $cardinality): void
    {
        $this->cardinality = $cardinality;
    }

    public function getCardinality(): int
    {
        return $this->cardinality;
    }

    public function getCardinalityRatio(): float
    {
        if (!$this->getTable()) {
            return 0;
        }

        // Identify the cardinality as a ratio of the size of the table
        // Cardinality in older version of MySQL aren't distinct per column
        return $this->getCardinality()
            ? $this->getTable()->getNumRows() / $this->getCardinality()
            : 0;
    }
}
