<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Table;

use Cadfael\Engine\Check;
use Cadfael\Engine\Report;
use Cadfael\Engine\Entity\Table;

class PreferredEngine implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Table
            && version_compare($entity->getSchema()->getDatabase()->getVersion(), '5.5') >= 0
            && !$entity->isVirtual();
    }

    public function run($entity): ?Report
    {
        if ($entity->information_schema->engine !== 'MyISAM') {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_CONCERN,
            [
                "This table uses the MyISAM engine instead of the InnoDB engine.",
                "MyISAM is not ACID compliant, does not support row-level locking, foreign keys or transactions."
            ]
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Preferred-Engine';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'InnoDB as preferred Table Engine';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "Since MySLQ 5.7 InnoDB has been the preferred engine over MyISAM.";
    }
}
