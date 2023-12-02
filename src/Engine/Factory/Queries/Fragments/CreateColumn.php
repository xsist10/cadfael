<?php

declare(strict_types=1);


namespace Cadfael\Engine\Factory\Queries\Fragments;

use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Entity\Column\InformationSchema;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Exception\UnknownCharacterSet;
use Cadfael\Engine\Exception\UnknownColumnType;
use Cadfael\Engine\Factory\Queries\Fragment;
use Cadfael\Utility\Types;

class CreateColumn extends Fragment
{
    /**
     * @throws UnknownCharacterSet
     * @throws UnknownColumnType
     */
    public function process(
        array $fragment,
        int $ordinal,
        string $default_character_set,
        string $default_collation
    ): Column {
        $sub_tree = $fragment['sub_tree'];

        $ref = $this->getSingleExpressionType($sub_tree, 'colref');
        $column_name = $this->extractLastPart($ref);

        // Extract column type definition
        $type = $this->getSingleExpressionType($sub_tree, 'column-type');
        $data_type = $this->getSingleExpressionType($type['sub_tree'], 'data-type');
        $comment = $this->getSingleExpressionType($type['sub_tree'], 'comment');

        $extras = [];
        // TODO: Add support for privileges
        // TODO: Add support for generation_expression
        $definition = [
            'ordinal_position' => $ordinal,
            'is_nullable' => $type['nullable'],
            'column_type' => $data_type['base_expr']
                . (!empty($data_type['length']) ? '(' . $data_type['length'] . ')' : '')
                . (!empty($data_type['unsigned']) ? ' unsigned' : ''),
            'data_type' => strtolower($data_type['base_expr']),
            'numeric_precision' => null,
            'numeric_scale' => null,
            'character_maximum_length' => null,
            'character_octet_length' => null,
            'character_set_name' => null,
            'collation_name' => null,
            'datetime_precision' => null,
            'default' => null,
            'column_key' => '',
            'comment' => ($comment ? $comment['base_expr'] : ''),
        ];

        // TODO: These two checks aren't being triggered by tests. Investigate.
        if ($type['primary']) {
            $this->log()->info("Setting column $column_name to PRIMARY KEY");
            $definition['column_key'] = 'PRI';
        } elseif ($type['unique']) {
            $definition['column_key'] = 'UNI';
        }

        if (Types::isString($data_type['base_expr'])) {
            // Extract character set and collation information
            $character_set = $this->getCharacterSetFromColumn($type);
            if ($character_set && !$this->validateCharacterSet($character_set)) {
                throw new UnknownCharacterSet("Invalid $character_set specified for column $column_name.");
            }
            $collation = $this->getCharacterSetCollation($type['sub_tree']);
            if (!$collation) {
                if ($character_set) {
                    // Default of the character set
                    $collation = $this->getDefaultCollationForCharacterSet($character_set);
                } else {
                    // Default of the table
                    $collation = $default_collation;
                }
            }
            // If no character set specified, fall back to default
            if (!$character_set) {
                $character_set = $default_character_set;
            }

            $definition['character_set_name'] = $character_set;
            $definition['collation_name'] = $collation;
        }


        if (isset($data_type['length'])) {
            if (Types::isNumeric($data_type['base_expr'])) {
                $definition['numeric_precision'] = (int)$data_type['length'];
            } elseif (Types::isString($data_type['base_expr'])) {
                $definition['character_maximum_length'] = (int)$data_type['length'];
                $definition['character_octet_length'] = min((int)$data_type['length'] * 4, 65535);
                // TODO: Cannot create a test for this. Explore more later.
            } elseif (Types::isTime($data_type['base_expr'])) {
                $definition['datetime_precision'] = (int)$data_type['length'];
            } else {
                throw new UnknownColumnType("Unknown length to type specific field: " . print_r($data_type, true));
            }
        }
        if (isset($type['default'])) {
            $definition['default'] = $type['default'];
        }

        if ($type['auto_inc']) {
            $extras[] = 'auto_increment';
        }
        if (str_contains($type['base_expr'], 'DEFAULT CURRENT_TIMESTAMP')) {
            $extras[] = 'DEFAULT_GENERATED';
        }
        if (str_contains($type['base_expr'], 'ON UPDATE CURRENT_TIMESTAMP')) {
            $extras[] = 'ON UPDATE CURRENT_TIMESTAMP';
        }
        $definition['extra'] = implode(' ', $extras);

        $column = new Column($column_name);
        $column->information_schema = InformationSchema::createFromStatement($definition);
        return $column;
    }

    private function getCharacterSetFromColumn(array $column_type): ?string
    {
        $character_set = $this->getSingleExpressionType($column_type['sub_tree'], 'character-set');
        if (!empty($character_set)) {
            return $character_set['base_expr'];
        }

        // TODO: Remove once the patch for Greenlion is upstreamed.
        $character_set = $this->getSingleExpressionTypeAndValue($column_type['sub_tree'], 'reserved', 'SET');
        if (empty($character_set)) {
            return null;
        }
        $encoding_tree = $this->getSingleExpressionType($character_set['sub_tree']['sub_tree'], 'colref');
        return $this->extractLastPart($encoding_tree);
    }
}
