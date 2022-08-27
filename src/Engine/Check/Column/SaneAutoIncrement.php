<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Column;

use Cadfael\Engine\Check;
use Cadfael\Engine\Report;
use Cadfael\Engine\Entity\Column;

class SaneAutoIncrement implements Check
{
    public function supports($entity): bool
    {
        // This check should only run on columns that are auto incrementing and not virtual
        return $entity instanceof Column
            && $entity->isAutoIncrementing()
            && !$entity->isVirtual()
            && !$entity->getTable()->isVirtual();
    }

    public function run($entity): ?Report
    {
        $messages = [];

        // Auto increment should be an unsigned integer type field
        if (!$entity->isInteger() || $entity->isSigned()) {
            $messages[] = 'This field should be an unsigned integer type.';
        }
        // It should be the primary key
        if (!$entity->isPartOfPrimaryKey()) {
            $messages[] = 'This field should be set as the primary key.';
        } else {
            // It should be the ONLY part of the primary key
            $primary_columns = $entity->getTable()->getPrimaryKeys();

            if (count($primary_columns) > 1) {
                $messages[] = 'This field should be a non-compound primary key.';
            }
        }

        if (count($messages)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_WARNING,
                $messages
            );
        }

        // Otherwise this column is fine
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
        return 'https://github.com/xsist10/cadfael/wiki/Sane-Auto-Increment';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Sane AUTO_INCREMENT definition';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "AUTO_INCREMENT definitions should follow some basic guidelines.";
    }
}
