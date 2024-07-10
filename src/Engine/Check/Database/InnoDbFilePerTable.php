<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Database;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Report;

/**
 * Class RequirePrimaryKey
 * @package Cadfael\Engine\Check\Schema
 */
class InnoDbFilePerTable implements Check
{
    public function supports($entity): bool
    {
        if (!$entity instanceof Database) {
            return false;
        }
        return true;
    }

    public function run($entity): ?Report
    {
        $variables = $entity->getVariables();
        $opt_name = 'innodb_file_per_table';
        $require_pk_disabled = (!isset($variables[$opt_name]) || $variables[$opt_name] !== 'ON');
        if ($require_pk_disabled) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_CONCERN,
                [
                    "You are running MySQL 8.0.13+ (MySQL " . $entity->getVersion() . ")"
                    .  " without innodb_file_per_table enabled.",
                    "It is recommended to turn this on.",
                ]
            );
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_OK,
            [ "You have innodb_file_per_table enabled." ]
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Enable-Innodb-File-Per-Table';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'InnoDB file per Table Configuration';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return 'Ensure each InnoDB table is managed in its own file.';
    }
}
