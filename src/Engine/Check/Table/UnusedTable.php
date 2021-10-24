<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Table;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Report;

class UnusedTable implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Table
            && !$entity->isVirtual();
    }

    public function run($entity): ?Report
    {
        if (is_null($entity->access_information)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        if ($entity->access_information->read_count || $entity->access_information->write_count) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK,
                [],
                [
                    "reads"  => $entity->access_information->read_count,
                    "writes" => $entity->access_information->write_count,
                ]
            );
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_WARNING,
            ["Table has not been written to or read from since the last server restart."]
        );
    }

    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Unused-Table';
    }

    public function getName(): string
    {
        return 'Unused Table';
    }

    public function getDescription(): string
    {
        return "An table that, since the server last restarted, hasn't been queried.";
    }
}
