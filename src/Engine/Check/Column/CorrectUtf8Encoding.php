<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Column;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Column;
use Cadfael\Engine\Report;

class CorrectUtf8Encoding implements Check
{
    public function supports($entity): bool
    {
        // This check should only run on columns
        return $entity instanceof Column;
    }

    public function run($entity): ?Report
    {
        if (is_null($entity->information_schema->character_set_name)) {
            return null;
        }

        if ($entity->information_schema->character_set_name !== 'utf8') {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        $reference = "https://www.eversql.com/mysql-utf8-vs-utf8mb4-whats-the-difference-between-utf8-and-utf8mb4/";
        return new Report(
            $this,
            $entity,
            Report::STATUS_CONCERN,
            [
                "Character set should be utf8mb4 not utf8.",
                "Reference: $reference"
            ]
        );
    }

    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Correct-UTF-8-Encoding';
    }

    public function getName(): string
    {
        return 'Correct UTF-8 Character Set Encoding';
    }

    public function getDescription(): string
    {
        return "UTF-8 character set should use utf8mb4 to avoid lossy storage of text.";
    }
}
