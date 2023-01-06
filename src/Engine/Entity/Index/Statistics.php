<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Index;

use Cadfael\Engine\Entity\Column;

/**
 * Class AccessInformation
 * @package Cadfael\Engine\Entity\Index
 * @codeCoverageIgnore
 */
class Statistics
{
    protected function __construct(
        public Column $column,
        public int $seq_in_index,
        public ?string $collation,
        public ?int $cardinality,
        public ?int $sub_part,
        public ?int $packed,
        public bool $nullable,
        public string $index_type,
        public string $comment,
        public string $index_comment,
        public ?bool $is_visible,
        public ?string $expression
    )
    {
    }

    /**
     * @param Column $column
     * @param array<string> $payload This is a raw record from information_schema.statistics
     * @return Statistics
     */
    public static function createFromInformationSchema(Column $column, array $payload): Statistics
    {
        return new Statistics(
            $column,
            (int)$payload['SEQ_IN_INDEX'],
            $payload['COLLATION'],
            (int)$payload['CARDINALITY'],
            (int)$payload['SUB_PART'],
            isset($payload['PACKED']) ? (int)$payload['PACKED'] : null,
            $payload['NULLABLE'] == 'YES',
            $payload['INDEX_TYPE'],
            $payload['COMMENT'],
            $payload['INDEX_COMMENT'],
            isset($payload['IS_VISIBLE']) ? (bool)$payload['IS_VISIBLE'] : null,
            $payload['EXPRESSION'] ?? null
        );
    }

    public static function getQuery(): string
    {
        return <<<EOF
            SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=:schema
EOF;
    }
}
