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
    public Column $column;
    public int $seq_in_index;
    public ?string $collation;
    public ?string $cardinality;
    public ?int $sub_part;
    public ?int $packed;
    public bool $nullable;
    public string $index_type;
    public string $comment;
    public string $index_comment;
    public ?bool $is_visible;
    public ?string $expression;

    protected function __construct()
    {
    }

    /**
     * @param Column $column
     * @param array<string> $payload This is a raw record from information_schema.statistics
     * @return Statistics
     */
    public static function createFromInformationSchema(Column $column, array $payload): Statistics
    {
        $statistics = new Statistics();
        $statistics->column = $column;
        $statistics->seq_in_index = (int)$payload['SEQ_IN_INDEX'];
        $statistics->collation = $payload['COLLATION'];
        $statistics->cardinality = $payload['CARDINALITY'];
        $statistics->sub_part = (int)$payload['SUB_PART'];
        $statistics->packed = isset($payload['PACKED']) ? (int)$payload['PACKED'] : null;
        $statistics->nullable = $payload['NULLABLE'] == 'YES';
        $statistics->index_type = $payload['INDEX_TYPE'];
        $statistics->comment = $payload['COMMENT'];
        $statistics->index_comment = $payload['INDEX_COMMENT'];
        $statistics->is_visible = isset($payload['IS_VISIBLE']) ? (bool)$payload['IS_VISIBLE'] : null;
        $statistics->expression = $payload['EXPRESSION'] ?? null;

        return $statistics;
    }
}
