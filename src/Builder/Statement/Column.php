<?php

declare(strict_types=1);


namespace Cadfael\Builder\Statement;

use Cadfael\Engine\Entity\Column as ColumnEntity;
use Cadfael\Engine\Entity\Column\InformationSchema;
use Cadfael\Engine\Exception\UnknownCharacterSet;
use Cadfael\Engine\Exception\UnknownColumnType;
use Cadfael\Utility\Types;
use SqlFtw\Sql\Ddl\Table\Column\ColumnDefinition;

class Column extends Fragment
{
    /**
     * @param ColumnDefinition $command
     * @return ColumnEntity
     * @throws UnknownColumnType
     * @throws UnknownCharacterSet
     */
    public static function createFromCommand(ColumnDefinition $command): ColumnEntity
    {
        $column = new ColumnEntity($command->getName());
        $type = $command->getType();
        $definition = [
            'ordinal_position' => 0,
            'is_nullable' => (bool)$command->getNullable(),
            'column_type' => strtolower($type->getBaseType()->getValue()
                . ($type->getSize() ? '(' . $type->getSize()[0] . ')' : '')
                . ($type->isUnsigned() ? ' unsigned' : '')),
            'data_type' => strtolower($type->getBaseType()->getValue()),
            'numeric_precision' => null,
            'numeric_scale' => null,
            'character_maximum_length' => null,
            'character_octet_length' => null,
            'character_set_name' => null,
            'collation_name' => null,
            'datetime_precision' => null,
            'default' => self::resolveValue($command->getDefaultValue()),
            'column_key' => '',
            'comment' => $command->getComment(),
        ];

        if ($command->getIndexType()) {
            $index = $command->getIndexType();
            switch ($index->getValue()) {
                case 'PRIMARY KEY':
                    $definition['column_key'] = 'PRI';
                    break;
                case 'UNIQUE':
                    $definition['column_key'] = 'UNI';
                    break;
            }
        }

        if ($type->getCharset()) {
            // Extract character set and collation information
            $character_set = $type->getCharset()->getValue();
            $definition['character_set_name'] = $character_set;
            $definition['collation_name'] = self::getDefaultCollationForCharacterSet($character_set);
        }

        if ($type->getCollation()) {
            // Extract collation information
            $collation = $type->getCollation()->getValue();
            // TODO: Validate that the collation matches the character set?
            $definition['collation_name'] = $collation;
        }

        if ($type->getSize()) {
            $size = $type->getSize()[0];
            if (Types::isNumeric($type->getBaseType()->getValue())) {
                $definition['numeric_precision'] = $size;
            } elseif (Types::isString($type->getBaseType()->getValue())) {
                $definition['character_maximum_length'] = $size;
                $definition['character_octet_length'] = min($size * 4, 65535);
            } elseif (Types::isTime($type->getBaseType()->getValue())) {
                $definition['datetime_precision'] = $size;
            } else {
                throw new UnknownColumnType("Unknown length to type specific field: " . print_r($type->getBaseType()->getValue(), true));
            }
        }

        $extras = [];
        if ($command->hasAutoincrement()) {
            $extras[] = 'auto_increment';
        }
        if ($definition['default'] === 'CURRENT_TIMESTAMP') {
            $extras[] = 'DEFAULT_GENERATED';
        }
        if ($command->getOnUpdate() && self::resolveValue($command->getOnUpdate()) === 'CURRENT_TIMESTAMP') {
            $extras[] = 'ON UPDATE CURRENT_TIMESTAMP';
        }
        $definition['extra'] = implode(' ', $extras);
        $column->information_schema = InformationSchema::createFromStatement($definition);
        return $column;
    }
}