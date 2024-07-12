<?php

declare(strict_types=1);


namespace Cadfael\Engine\Check\Database;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Database\SqlModes;
use Cadfael\Engine\Exception\MySQL\UnknownVersion;
use Cadfael\Engine\Report;

/**
 * Class RequirePrimaryKey
 * @package Cadfael\Engine\Check\Schema
 */
class StrictSqlMode implements Check
{
    public function supports($entity): bool
    {
        if (!$entity instanceof Database) {
            return false;
        }
        // If we can't determine the version we can't do this check
        try {
            $version = $entity->getVersion();
            // We only want to examine MySQL versions >= 8.0.0.
            // TODO: Add sane defaults for < 8.0.0
            return version_compare($version, '8.0.0', '>=');
        } catch (UnknownVersion $e) {
            return false;
        }
    }

    public function run($entity): ?Report
    {
        $variables = $entity->getVariables();
        $sql_mode = SqlModes::normaliseMode($variables['sql_mode']);

        $has_strict_all_tables = in_array('STRICT_ALL_TABLES', $sql_mode) !== false;
        $has_strict_trans_tables = in_array('STRICT_TRANS_TABLES', $sql_mode) !== false;

        if (!$has_strict_all_tables && !$has_strict_trans_tables) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_WARNING,
                [
                    "It is recommend that you run in strict mode.",
                    "Either STRICT_ALL_TABLES or STRICT_TRANS_TABLES should be enabled."
                ]
            );
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_OK
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Sql-Strict-Mode';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Strict SQL Mode';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return 'Recommend that sql strict mode is set avoid data truncation, bad charset conversion, or other issues.';
    }
}
