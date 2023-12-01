<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Table;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Report;

class MustHavePrimaryKey implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Table
            && !$entity->isVirtual();
    }

    public function run($entity): ?Report
    {
        if (count($entity->getPrimaryKeys()) > 0) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_CRITICAL,
            [ "Table SHOULD have a PRIMARY KEY" ]
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Must-Have-Primary-Key';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Table must have PRIMARY KEY';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "All tables should have a PRIMARY KEY specified.";
    }
}
