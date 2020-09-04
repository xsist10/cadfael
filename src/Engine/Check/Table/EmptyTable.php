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
        if (!empty($entity->information_schema->table_rows)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        $auto_increment_issued = 0;
        if ($entity->schema_auto_increment_column) {
            $auto_increment_issued = $entity->schema_auto_increment_column->auto_increment - 1;
        }
        if ($entity->information_schema->data_free > 0 || $auto_increment_issued) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_INFO,
                [ "Table is empty but has free space. It is probably used as a some form of queue." ]
            );
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_WARNING,
            [ "Table contains no records." ]
        );
    }
}
