<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Database;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Exception\MySQL\UnknownVersion;
use Cadfael\Engine\Report;

/**
 * Class UnsupportedVersion
 * @package Cadfael\Engine\Check\Schema
 * @codeCoverageIgnore Time based checks are awful to unit test unless you inject the time
 */
class UnsupportedVersion implements Check
{
    const SUPPORT_EOL_TIMESTAMP = [
        "8.0.0" => 1774998001, // April 2026
        "5.7.0" => 1696118401, // Oct 2023
        "5.6.0" => 1612137601, // Feb 2021
        "5.5.0" => 1543622401, // Dec 2018
        "5.1.0" => 1385856001, // Dec 2013
    ];

    protected function getVersionEOL(string $active_version): int
    {
        foreach (self::SUPPORT_EOL_TIMESTAMP as $version => $eol) {
            if (version_compare($active_version, $version) >= 0) {
                return $eol;
            }
        }

        throw new UnknownVersion("$active_version is not a valid MySQL version.");
    }

    public function supports($entity): bool
    {
        return $entity instanceof Database;
    }

    public function run($entity): ?Report
    {
        try {
            $eol = $this->getVersionEOL($entity->getVersion());
        } catch (UnknownVersion $e) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_CONCERN,
                [
                    $e->getMessage(),
                    "This makes us nervous."
                ]
            );
        }

        $eol_date = date("M Y", $eol);

        // If we have passed End of Life (EOL), we should raise a critical report.
        if ($eol < time()) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_CRITICAL,
                [
                    "Your version of MySQL (" . $entity->getVersion() . ") is no longer supported.",
                    "This means it is no longer receiving security patches.",
                    "Please upgrade to a supported version ASAP.",
                    "Reference: https://en.wikipedia.org/wiki/MySQL#Release_history"
                ]
            );
        }

        // If we are within 6 months of End of Life, we should raise a warning report.
        $half_a_year_in_seconds = (3600 * 24 * (356 / 2));
        if ($eol < time() + $half_a_year_in_seconds) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_WARNING,
                [
                    "Your version of MySQL (" . $entity->getVersion() . ") will end support on $eol_date.",
                    "This means it will no longer receiving security patches beyond that date.",
                    "Ensure you have planned how to remove blockers to upgrading to a newer version.",
                    "Reference: https://en.wikipedia.org/wiki/MySQL#Release_history"
                ]
            );
        }

        return new Report(
            $this,
            $entity,
            Report::STATUS_OK,
            [ "Your version of MySQL (" . $entity->getVersion() . ") is supported until $eol_date." ]
        );
    }

    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Unsupported-Version';
    }

    public function getName(): string
    {
        return 'Unsupported MySQL version';
    }

    public function getDescription(): string
    {
        return 'Spot if your MySQL version is out of support and no longer receiving security updates.';
    }
}
