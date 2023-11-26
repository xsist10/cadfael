<?php

declare(strict_types=1);


namespace Cadfael\Utility;

class Types
{
    public const DATA_TYPES = [
        'bigint',
        'binary',
        'bit',
        'blob',
        'char',
        'date',
        'datetime',
        'decimal',
        'double',
        'enum',
        'float',
        'geometry',
        'geometrycollection',
        'int',
        'json',
        'linestring',
        'longblob',
        'longtext',
        'mediumblob',
        'mediumint',
        'mediumtext',
        'multilinestring',
        'multipoint',
        'multipolygon',
        'point',
        'polygon',
        'set',
        'smallblob',
        'smallint',
        'text',
        'time',
        'timestamp',
        'tinyblob',
        'tinyint',
        'tinytext',
        'varbinary',
        'varchar',
        'year',
    ];

    public const PREFIX_ALLOWED_DATA_TYPES = [
        'char',
        'varchar',
        'binary',
        'varbinary'
    ];

    public static function isInteger($data_type): bool
    {
        return in_array(strtolower($data_type), ['tinyint', 'smallint', 'mediumint', 'int', 'bigint' ]);
    }

    public static function isNumeric($data_type): bool
    {
        return self::isInteger($data_type)
            || in_array(strtolower($data_type), [ 'bit', 'decimal', 'double', 'float' ]);
    }

    public static function isString($data_type): bool
    {
        $string_types = [ 'char', 'longtext', 'mediumtext', 'text', 'tinytext', 'varchar' ];
        return in_array(strtolower($data_type), $string_types);
    }

    public static function isBinary($data_type): bool
    {
        return in_array(strtolower($data_type), ['binary', 'varbinary']);
    }

    public static function isTime($data_type): bool
    {
        return in_array(strtolower($data_type), ['date', 'datetime', 'time', 'timestamp', 'year']);
    }

    public static function isPrefixAllowed($data_type): bool
    {
        return in_array(strtolower($data_type), self::PREFIX_ALLOWED_DATA_TYPES);
    }
}
