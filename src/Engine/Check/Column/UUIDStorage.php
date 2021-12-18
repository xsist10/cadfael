<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Check\Column;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Report;

class UUIDStorage implements Check
{
    public function supports($entity): bool
    {
        // This check should only run on columns
        return $entity instanceof Column;
    }

    public function run($entity): ?Report
    {
        // We can try to detect if a column is a UUID (E.g.: 8f2cda13-357f-11ec-a9d6-4827ea22e92c) based on:
        // - Type: [VAR]CHAR(34|36) or [VAR]BINARY(16)
        // - Name: (contains UUID or ID)
        $length = $entity->information_schema->character_maximum_length;
        $is_binary = $entity->isBinary() && $length === 16;
        $is_string_of_appropriate_length = $entity->isString()
            && ($length === 34 || $length === 36);
        $is_type_match = $is_binary || $is_string_of_appropriate_length;

        $is_name_match = stripos($entity->getName(), 'id') !== false
            || stripos($entity->getName(), 'uuid') !== false;

        // If we don't suspect this field of being a UUID, we can just skip any report
        if (!$is_name_match || !$is_type_match) {
            return null;
        }

        if ($is_binary) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        } else {
            return new Report(
                $this,
                $entity,
                Report::STATUS_CONCERN,
                [
                    'You are storing a UUID in a string field type. Instead you should use BINARY(16)',
                    'If you are concerned about ordering, consider exploring UUIDv6, 7 or 8.'
                ]
            );
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/UUID-Storage';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'UUID Storage';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return 'Verify that a column storing UUIDs is correctly structured.';
    }
}
