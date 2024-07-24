<?php

declare(strict_types=1);

namespace Cadfael\Engine\Factory\Queries;

use Cadfael\Engine\Exception\UnknownCharacterSet;
use Cadfael\Engine\Exception\UnknownCollation;
use Cadfael\NullLoggerDefault;
use Psr\Log\LoggerAwareTrait;

abstract class Fragment
{
    use LoggerAwareTrait, NullLoggerDefault;

    const DEFAULT_CHARACTER_SET_COLLATIONS = [
        'armscii8'  => 'armscii8_general_ci',
        'ascii'     => 'ascii_general_ci',
        'big5'      => 'big5_chinese_ci',
        'binary'    => 'binary',
        'cp1250'    => 'cp1250_general_ci',
        'cp1251'    => 'cp1251_general_ci',
        'cp1256'    => 'cp1256_general_ci',
        'cp1257'    => 'cp1257_general_ci',
        'cp850'     => 'cp850_general_ci',
        'cp852'     => 'cp852_general_ci',
        'cp866'     => 'cp866_general_ci',
        'cp932'     => 'cp932_japanese_ci',
        'dec8'      => 'dec8_swedish_ci',
        'eucjpms'   => 'eucjpms_japanese_ci',
        'euckr'     => 'euckr_korean_ci',
        'gb18030'   => 'gb18030_chinese_ci',
        'gb2312'    => 'gb2312_chinese_ci',
        'gbk'       => 'gbk_chinese_ci',
        'geostd8'   => 'geostd8_general_ci',
        'greek'     => 'greek_general_ci',
        'hebrew'    => 'hebrew_general_ci',
        'hp8'       => 'hp8_english_ci',
        'keybcs2'   => 'keybcs2_general_ci',
        'koi8r'     => 'koi8r_general_ci',
        'koi8u'     => 'koi8u_general_ci',
        'latin1'    => 'latin1_swedish_ci',
        'latin2'    => 'latin2_general_ci',
        'latin5'    => 'latin5_turkish_ci',
        'latin7'    => 'latin7_general_ci',
        'macce'     => 'macce_general_ci',
        'macroman'  => 'macroman_general_ci',
        'sjis'      => 'sjis_japanese_ci',
        'swe7'      => 'swe7_swedish_ci',
        'tis620'    => 'tis620_thai_ci',
        'ucs2'      => 'ucs2_general_ci',
        'ujis'      => 'ujis_japanese_ci',
        'utf16le'   => 'utf16le_general_ci',
        'utf16'     => 'utf16_general_ci',
        'utf32'     => 'utf32_general_ci',
        'utf8mb3'   => 'utf8mb3_general_ci',
        'utf8mb4'   => 'utf8mb4_0900_ai_ci',
    ];

    /**
     * Get the default character set collation for a character set.
     *
     * @param string $character_set
     * @return string
     * @throws UnknownCharacterSet
     */
    public function getDefaultCollationForCharacterSet(string $character_set): string
    {
        if (isset(self::DEFAULT_CHARACTER_SET_COLLATIONS[$character_set])) {
            return self::DEFAULT_CHARACTER_SET_COLLATIONS[$character_set];
        }
        throw new UnknownCharacterSet("$character_set is an unknown character set.");
    }

    public function validateCharacterSet(string $character_set): bool
    {
        return isset(self::DEFAULT_CHARACTER_SET_COLLATIONS[$character_set]);
    }

    /**
     * Find all array structures where the `expr_type` matches $type.
     *
     * @param array $sub_tree
     * @param string $type
     * @return array
     */
    protected function getExpressionType(array $sub_tree, string $type): array
    {
        return array_filter($sub_tree, function ($element) use ($type) {
            return $element['expr_type'] === $type;
        });
    }

    /**
     * Find the first array structures where the `expr_type` matches $type.
     *
     * @param array $sub_tree
     * @param string $type
     * @return array|null
     */
    protected function getSingleExpressionType(array $sub_tree, string $type): ?array
    {
        $expressions = $this->getExpressionType($sub_tree, $type);
        return array_shift($expressions);
    }

    /**
     * Find all array structures where the `expr_type` matches $type and the `base_expr` matches $value.
     *
     * @param array $sub_tree
     * @param string $type
     * @param string $value
     * @return array
     */
    protected function getExpressionTypeAndValue(array $sub_tree, string $type, string $value): array
    {
        return array_filter($sub_tree, function ($element) use ($type, $value) {
            return $element['expr_type'] === $type && strtolower($element['base_expr']) === strtolower($value);
        });
    }

    /**
     * Find the first array structures where the `expr_type` matches $type and the `base_expr` matches $value.
     *
     * @param array $sub_tree
     * @param string $type
     * @param string $value
     * @return array|null
     */
    protected function getSingleExpressionTypeAndValue(array $sub_tree, string $type, string $value): ?array
    {
        $expressions = $this->getExpressionTypeAndValue($sub_tree, $type, $value);
        return array_shift($expressions);
    }

    /**
     * A number of places exist where there is a structure that contains a base_expr as well as the base_expr broken up
     * into unquoted parts. Example:
     *
     * [
     *     'base_expr' => '`schema`.`table`',
     *     'no_quotes' => [
     *         'parts' => [
     *             'schema',
     *             'table'
     *         ]
     *     ]
     * ]
     *
     * This function extracts the `parts` array. If that is not available, it returns an array of one element containing
     * the `base_expr` value.
     *
     * @param array $sub_tree
     * @return array
     */
    protected function extractParts(array $sub_tree): array
    {
        if (!isset($sub_tree['no_quotes'])) {
            return [ $sub_tree['base_expr'] ];
        }

        return $sub_tree['no_quotes']['parts'];
    }

    /**
     * A number of places exist where there is a structure that contains a base_expr as well as the base_expr broken up
     * into unquoted parts. Example:
     *
     * [
     *     'base_expr' => '`schema`.`table`',
     *     'no_quotes' => [
     *         'parts' => [
     *             'schema',
     *             'table'
     *         ]
     *     ]
     * ]
     *
     * This function takes this structure and extracts the last entry in the `parts` array since this is often the
     * string we desire (table name, column name, etc.). If no `no_quotes` key exists then we'll fall back to using the
     * `base_expr` key.
     *
     * @param array $sub_tree
     * @return string
     */
    protected function extractLastPart(array $sub_tree): string
    {
        $parts = $this->extractParts($sub_tree);
        if (count($parts)) {
            return array_pop($parts);
        }
        return '';
    }

    protected function getCharacterSetCollation(array $sub_tree): ?string
    {
        $collation = $this->getSingleExpressionType($sub_tree, 'collation');
        if (empty($collation)) {
            return null;
        }

        return $collation['base_expr'];
    }
}
