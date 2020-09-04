<?php

declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check;

use Cadfael\Engine\Entity\Column;

class ColumnBuilder
{
    const BASE = [
        "TABLE_NAME"                => "MOCK_TABLE",
        "COLUMN_NAME"               => "MOCK_COLUMN",
        "ORDINAL_POSITION"          => "1",
        "COLUMN_DEFAULT"            => "0",
        "IS_NULLABLE"               => "NO",
        "DATA_TYPE"                 => "int",
        "CHARACTER_MAXIMUM_LENGTH"  => NULL,
        "CHARACTER_OCTET_LENGTH"    => NULL,
        "NUMERIC_PRECISION"         => "10",
        "NUMERIC_SCALE"             => "0",
        "DATETIME_PRECISION"        => NULL,
        "CHARACTER_SET_NAME"        => NULL,
        "COLLATION_NAME"            => NULL,
        "COLUMN_TYPE"               => "int(20)",
        "COLUMN_KEY"                => "",
        "EXTRA"                     => "",
        "PRIVILEGES"                => "select",
        "COLUMN_COMMENT"            => "",
        "GENERATION_EXPRESSION"     => "",
    ];

    private $override = [];

    public function name($name): ColumnBuilder
    {
        $this->override['COLUMN_NAME'] = $name;
        return $this;
    }

    public function character_encoding($encoding): ColumnBuilder
    {
        $this->override['CHARACTER_SET_NAME'] = $encoding;
        return $this;
    }

    public function nullable(): ColumnBuilder
    {
        $this->override['IS_NULLABLE'] = true;
        return $this;
    }

    public function primary(): ColumnBuilder
    {
        $this->override["COLUMN_KEY"] = "PRI";
        return $this;
    }

    public function auto_increment(): ColumnBuilder
    {
        $this->override["EXTRA"] = "auto_increment";
        return $this;
    }

    public function unsigned(): ColumnBuilder
    {
        $this->override['unsigned'] = true;
        $this->override["COLUMN_TYPE"] = $this->override["DATA_TYPE"] . "(" . $this->override["NUMERIC_PRECISION"] . ") unsigned";
        return $this;
    }

    public function int(int $precision = 10): ColumnBuilder
    {
        $this->override["DATA_TYPE"] = "int";
        $this->override["NUMERIC_PRECISION"] = $precision;
        $this->override["COLUMN_TYPE"] = "int(" . $precision . ")" . (!empty($this->override['unsigned']) ? ' unsigned' : '');
        return $this;
    }

    public function varchar(int $length = 10): ColumnBuilder
    {
        $this->override["DATA_TYPE"] = "varchar";
        $this->override["CHARACTER_MAXIMUM_LENGTH"] = $length;
        $this->override["COLUMN_TYPE"] = "varchar($length)";
        return $this;
    }

    public function generated(): ColumnBuilder
    {
        $this->override["GENERATION_EXPRESSION"] = "3.141592 * 42";
        return $this;
    }

    public function generate(): Column
    {
        $column = Column::createFromInformationSchema(array_merge(
            self::BASE,
            $this->override
        ));
        $this->override = [];

        return $column;
    }
}