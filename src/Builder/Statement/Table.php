<?php

declare(strict_types=1);


namespace Cadfael\Builder\Statement;

use Cadfael\Engine\Entity\Column as ColumnEntity;
use Cadfael\Engine\Entity\Index;
use Cadfael\Engine\Entity\Index\Statistics;
use Cadfael\Engine\Entity\Table as TableEntity;
use Cadfael\Engine\Entity\Table\InformationSchema;
use Cadfael\Engine\Exception\ExistingColumn;
use Cadfael\Engine\Exception\InvalidColumn;
use Cadfael\Engine\Exception\InvalidIndexType;
use Cadfael\Engine\Exception\QueryParseException;
use Cadfael\Engine\Exception\UnknownCharacterSet;
use Cadfael\Engine\Exception\UnknownCollation;
use Cadfael\Engine\Exception\UnknownColumnType;
use SqlFtw\Sql\Ddl\Table\Alter\Action\AddColumnAction;
use SqlFtw\Sql\Ddl\Table\Alter\Action\AddIndexAction;
use SqlFtw\Sql\Ddl\Table\AlterTableCommand;
use SqlFtw\Sql\Ddl\Table\Column\ColumnDefinition;
use SqlFtw\Sql\Ddl\Table\CreateTableCommand;
use SqlFtw\Sql\Ddl\Table\Index\IndexDefinition;
use SqlFtw\Sql\Ddl\Table\Option\TableOption;
use SqlFtw\Sql\InvalidDefinitionException;

class Table extends Fragment
{
    /**
     * @param AlterTableCommand $command
     * @param TableEntity $table
     * @return TableEntity
     * @throws ExistingColumn
     * @throws InvalidIndexType
     * @throws QueryParseException
     * @throws UnknownCharacterSet
     * @throws UnknownCollation
     * @throws UnknownColumnType
     * @throws InvalidColumn
     */
    public static function alterFromCommand(AlterTableCommand $command, TableEntity $table): TableEntity
    {
        $ordinal = count($table->getColumns()) + 1;
        $collation_name = $table->information_schema->table_collation;
        $character_set = Fragment::getCharacterSetFromCollation($collation_name);
        foreach ($command->getActions() as $action) {
            switch (true) {
                case $action instanceof AddColumnAction:
                    $definition = $action->getColumn();
                    if ($table->hasColumn($definition->getName())) {
                        throw new ExistingColumn("Column " . $definition->getName() . " already exists.");
                    }
                    $column = self::generateColumnDefinition($definition, $table, $character_set, $collation_name);
                    $column->information_schema->ordinal_position = $ordinal;
                    $table->addColumn($column);
                    $ordinal++;
                    break;
                case $action instanceof AddIndexAction:
                    $definition = $action->getIndex();
                    $table->addIndex(self::generateIndexDefinition($definition, $table));
                    break;
                default:
                    print_r($action);
                    throw new QueryParseException("Uncertain on how to handle this alter action: " . get_class($action));
            }
        }

        return $table;
    }

    /**
     * @param CreateTableCommand $command
     * @return TableEntity
     * @throws InvalidColumn
     * @throws InvalidDefinitionException
     * @throws InvalidIndexType
     * @throws UnknownCharacterSet
     * @throws UnknownColumnType
     */
    public static function createFromCommand(CreateTableCommand $command): TableEntity
    {
        $table = new TableEntity($command->getTable()->getName());

        // Default character set and collation
        $character_set = 'latin1';
        $collation_name = self::getDefaultCollationForCharacterSet($character_set);

        // Extract the character sets specified on the table (if set).
        $options = $command->getOptionsList();
        if ($options->get(TableOption::CHARACTER_SET)) {
            $character_set = $options->get(TableOption::CHARACTER_SET)->getValue();
        }
        if ($options->get(TableOption::COLLATE)) {
            $collation_name = $options->get(TableOption::COLLATE)->getValue();
        }

        $table->information_schema = InformationSchema::createFromInformationSchema(
            self::getTableInformationSchema($options)
        );

        $columns = [];
        $ordinal = 1;
        foreach ($command->getItems() as $item) {
            if ($item instanceof ColumnDefinition) {
                $column = self::generateColumnDefinition($item, $table, $character_set, $collation_name);
                $column->information_schema->ordinal_position = $ordinal;
                $columns[] = $column;
                $ordinal++;
            }
        }
        $table->setColumns(...$columns);

        foreach ($command->getItems() as $item) {
            if ($item instanceof IndexDefinition) {
                $table->addIndex(self::generateIndexDefinition($item, $table));
            }
        }
        return $table;
    }


    /**
     * @param ColumnDefinition $item
     * @param TableEntity $table
     * @param string $default_character_set
     * @param string $default_collation_name
     * @return ColumnEntity
     * @throws InvalidIndexType
     * @throws UnknownCharacterSet
     * @throws UnknownColumnType
     */
    public static function generateColumnDefinition(
        ColumnDefinition $item,
        TableEntity $table,
        string $default_character_set,
        string $default_collation_name
    ): ColumnEntity
    {
        $column = Column::createFromCommand($item);
        $column->setTable($table);
        if ($column->isString()) {
            if (!$column->information_schema->character_set_name) {
                $column->information_schema->character_set_name = $default_character_set;
            }
            if (!$column->information_schema->collation_name) {
                $column->information_schema->collation_name = $default_collation_name;
            }
        }

        // If the index is inlined, handle it here
        if ($item->getIndexType()) {
            switch ($item->getIndexType()->getValue()) {
                case 'PRIMARY KEY':
                    $index = new Index("PRIMARY");
                    $index->setUnique(true);
                    $statistic = Statistics::createFromStatement($column);
                    $index->addStatistics($statistic);
                    $table->addIndex($index);
                    break;
                case 'UNIQUE INDEX':
                    $index = new Index("UNIQUE KEY");
                    $index->setUnique(true);
                    $statistic = Statistics::createFromStatement($column);
                    $index->addStatistics($statistic);
                    $table->addIndex($index);
                    break;
                default:
                    throw new InvalidIndexType('Unknown index type: ' . $item->getIndexType()->getValue());
            }
        }
        return $column;
    }

    /**
     * @param IndexDefinition $item
     * @param TableEntity $table
     * @return Index
     * @throws InvalidColumn
     * @throws InvalidIndexType
     */
    public static function generateIndexDefinition(IndexDefinition $item, TableEntity $table): Index
    {
        switch ($item->getType()->getValue()) {
            case 'PRIMARY KEY':
                $index = new Index("PRIMARY");
                $index->setUnique(true);
                foreach ($item->getParts() as $part) {
                    $column = $table->getColumn($part->getExpression());
                    $column->information_schema->column_key = 'PRI';
                    $statistic = Statistics::createFromStatement($column);
                    $index->addStatistics($statistic);
                }
                return $index;
            case 'UNIQUE INDEX':
                foreach ($item->getParts() as $part) {
                    $column = $table->getColumn($part->getExpression());
                    // This column might already be defined as a PRIMARY KEY. Don't overwrite with UNIQUE
                    if ($column->information_schema->column_key != 'PRI') {
                        $column->information_schema->column_key = 'UNI';
                    }
                }
            case 'INDEX':
                $index = new Index($item->getName() ?? "unknown_index");
                $index->setUnique($item->getType()->getValue() === 'UNIQUE INDEX');
                foreach ($item->getParts() as $part) {
                    $column = $table->getColumn($part->getExpression());
                    $statistic = Statistics::createFromStatement($column);
                    if ($part->getLength()) {
                        $statistic->sub_part = $part->getLength();
                    }
                    $index->addStatistics($statistic);
                }
                return $index;
            default:
                throw new InvalidIndexType('Unknown index type: ' . $item->getType()->getValue());
        }
    }

    /**
     * @param $options
     * @return array
     */
    public static function getTableInformationSchema($options): array
    {
        $mappings = [
            'TABLE_COLLATION' => TableOption::COLLATE,
            'ENGINE' => TableOption::ENGINE,
            'AUTO_INCREMENT' => TableOption::AUTO_INCREMENT,
            'ROW_FORMAT' => TableOption::ROW_FORMAT,
        ];

        $information_schema = [];
        foreach ($mappings as $key => $option) {
            if ($options->get($option)) {
                if (is_object($options->get($option))) {
                    $information_schema[$key] = $options->get($option)->getValue();
                } else {
                    $information_schema[$key] = $options->get($option);
                }
            }
        }
        return $information_schema;
    }
}