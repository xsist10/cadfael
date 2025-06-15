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
            // We only want to examine MySQL versions >= 5.7.0 when the feature was added
            return version_compare($version, '5.7.0', '>=');
        } catch (UnknownVersion $e) {
            return false;
        }
    }

    public function run($entity): ?Report
    {
        $variables = $entity->getVariables();
        $version = $entity->getVersion();
        $sql_mode = SqlModes::normaliseMode($variables['sql_mode'], $version);

        $has_strict_all_tables = SqlModes::hasMode($sql_mode, 'STRICT_ALL_TABLES');
        $has_strict_trans_tables = SqlModes::hasMode($sql_mode, 'STRICT_TRANS_TABLES');
        $no_auto_create_user = SqlModes::hasMode($sql_mode, 'NO_AUTO_CREATE_USER');

        $warnings = [];
        $info = [];

        // Recommend that the database is run in strict mode
        if (version_compare($version, '5.7.0', '>=')) {
            if (!$has_strict_all_tables && !$has_strict_trans_tables) {
                $warnings[] = "It is recommend that you run in strict mode.";
                $warnings[] = "Either STRICT_ALL_TABLES or STRICT_TRANS_TABLES should be enabled.";
            }
        }

        $error_for_division_by_zero = SqlModes::hasMode($sql_mode, 'ERROR_FOR_DIVISION_BY_ZERO');
        $no_zero_date = SqlModes::hasMode($sql_mode, 'NO_ZERO_DATE');
        $no_zero_in_date = SqlModes::hasMode($sql_mode, 'NO_ZERO_IN_DATE');

        // For all versions from 5.7.8 until before 8.x releases, ensure that if STRICT_ALL_TABLES or
        // STRICT_TRANS_TABLES are set, that the server also includes NO_ZERO_DATE, NO_ZERO_IN_DATE and
        // ERROR_FOR_DIVISION_BY_ZERO as they are merged into the strict modes from 8.x onwards.
        if (version_compare($version, '5.7.8', '>=') && version_compare($version, '8.0.0', '<')) {
            $message = "%s should be enabled if you have STRICT_ALL_TABLES or STRICT_TRANS_TABLES enabled.";
            if ($has_strict_all_tables || $has_strict_trans_tables) {
                if (!$error_for_division_by_zero) {
                    $warnings[] = sprintf($message, "ERROR_FOR_DIVISION_BY_ZERO");
                }
                if (!$no_zero_date) {
                    $warnings[] = sprintf($message, "NO_ZERO_DATE");
                }
                if (!$no_zero_in_date) {
                    $warnings[] = sprintf($message, "NO_ZERO_IN_DATE");
                }
            }
        }

        // NO_AUTO_CREATE_USER isn't needed after MySQL 8.0.11
        if (version_compare($version, '8.0.11', '>=') && $no_auto_create_user) {
            $info[] = "NO_AUTO_CREATE_USER was dropped in MySQL 8.0.11 and is now the default behaviour.";
        }

        if (count($warnings)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_WARNING,
                array_merge($warnings, $info)
            );
        }
        if (count($info)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_INFO,
                $info
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
