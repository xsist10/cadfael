<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Check\Table;

use Cadfael\Engine\Check;
use Cadfael\Engine\Report;
use Cadfael\Engine\Entity\Table;

class EmptyTable implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Table
            && !$entity->isVirtual();
    }

    public function run($entity): ?Report
    {
        if ($entity->getNumRows()) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        // Find out what the next auto-increment value is. The assumption is
        // that if this value is > 1, then entries have been issued. This is
        // only effective if the table has an auto-increment column.
        $auto_increment_column = $entity->getSchemaAutoIncrementColumn();
        $auto_increment_issued = 0;
        if ($auto_increment_column !== null) {
            $auto_increment_issued = $auto_increment_column->auto_increment
                ? $auto_increment_column->auto_increment - 1
                : 0;
        }

        if ($auto_increment_issued) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_CONCERN,
                [
                    "Table is empty but previously had records inserted.",
                    "It is possible it is used as a some form of queue or has had all records deleted."
                ]
            );
        }

        if ($entity->information_schema->data_free) {
            $messages = [
                "Table is empty but has allocated free space.",
            ];

            // Is this table in a weird tablespace?
            if ($entity->getTablespaceType() === "System") {
                // If so, we can't rely on data free to give us an indication of
                // the usage of this table.
                $messages[] = "This table is in a shared tablespace so this doesn't mean much.";
            } else {
                $messages[] = "It is possible it is used as a some form of queue or has had all records deleted.";
            }

            return new Report(
                $this,
                $entity,
                Report::STATUS_CONCERN,
                $messages
            );
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_WARNING,
            [ "Table contains no records." ]
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Empty-Table';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Empty table';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "Empty tables add unnecessary cognitive load similar to dead code.";
    }
}
