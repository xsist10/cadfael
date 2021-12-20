<?php

declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check;

use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Index\Statistics;

class IndexBuilder
{
    const BASE = [
        "TABLE_CATALOG" => "def",
        "TABLE_SCHEMA" => "MOCK_SCHEMA",
        "TABLE_NAME" => "MOCK_TABLE",
        "NON_UNIQUE" => "1",
        "INDEX_SCHEMA" => "MOCK_SCHEMA",
        "INDEX_NAME" => "MOCK_INDEX",
        "SEQ_IN_INDEX" => "1",
        "COLUMN_NAME" => "MOCK_COLUMN",
        "COLLATION" => "A",
        "CARDINALITY" => "0",
        "SUB_PART" => NULL,
        "PACKED" => NULL,
        "NULLABLE" => "",
        "INDEX_TYPE" => "BTREE",
        "COMMENT" => "",
        "INDEX_COMMENT" => "",
        "IS_VISIBLE" => "YES",
        "EXPRESSION" => NULL,
    ];

    private $override = [];
    private ?Column $column = null;

    public function name($name): IndexBuilder
    {
        $this->override['INDEX_NAME'] = $name;
        return $this;
    }

    public function cardinality($cardinality): IndexBuilder
    {
        $this->override['CARDINALITY'] = $cardinality;
        return $this;
    }

    public function isUnique(bool $unique): IndexBuilder
    {
        $this->override['NON_UNIQUE'] = $unique ? "0" : "1";
        return $this;
    }

    public function setSubPart(int $sub_part) {
        $this->override['SUB_PART'] = $sub_part;
        return $this;
    }

    public function setCardinality(int $cardinality) {
        $this->override['CARDINALITY'] = $cardinality;
        return $this;
    }

    public function setColumn(Column $column): IndexBuilder
    {
        $this->column = $column;
        return $this;
    }

    public function generate(): Index
    {
        $statistics = array_merge(
            self::BASE,
            $this->override
        );

        $column = $this->column;
        if (!$column) {
            $builder = new ColumnBuilder();
            $column = $builder->name($statistics['INDEX_NAME'])
                ->generate();
        }

        $index = new Index($statistics['INDEX_NAME']);
        $index->setUnique(!(bool)$statistics['NON_UNIQUE']);
        $index->setStatistics(Statistics::createFromInformationSchema($column, $statistics));

        $this->override = [];
        $this->column = null;

        return $index;
    }
}