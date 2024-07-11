<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Database;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Exception\MySQL\UnknownVersion;
use Cadfael\Engine\Report;
use DateInterval;
use DateTimeImmutable;

/**
 * Class UnsupportedVersion
 * @package Cadfael\Engine\Check\Schema
 * @codeCoverageIgnore Time based checks are awful to unit test unless you inject the time
 */
class UnsupportedVersion implements Check
{
    const SUPPORT_EOL_DATE = [
        // There is no official EoL, but we're projecting 8 years based on previous support
        "8.1.0" => "2031-06-01", // June 2031
        "8.0.0" => "2026-04-01", // April 2026
        "5.7.0" => "2023-10-01", // Oct 2023
        "5.6.0" => "2021-02-01", // Feb 2021
        "5.5.0" => "2018-12-01", // Dec 2018
        "5.1.0" => "2013-12-01", // Dec 2013
    ];
    protected DateTimeImmutable $current_date;

    public function __construct(DateTimeImmutable $current_date = new DateTimeImmutable())
    {
        $this->current_date = $current_date;
    }

    /**
     * @throws UnknownVersion
     * @throws \Exception
     */
    protected function getVersionEOL(string $active_version): DateTimeImmutable
    {
        foreach (self::SUPPORT_EOL_DATE as $version => $eol) {
            if (version_compare($active_version, $version) >= 0) {
                return new DateTimeImmutable($eol);
            }
        }

        throw new UnknownVersion("$active_version is not a supported version of MySQL.");
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

        // If we have passed End of Life (EOL), we should raise a critical report.
        if ($eol < $this->current_date) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_CRITICAL,
                [
                    "Your version of MySQL (" . $entity->getVersion() . ") is no longer supported.",
                    "This means it is no longer receiving security patches.",
                    "Please upgrade to a supported version ASAP."
                ]
            );
        }

        // Pretty label
        $eol_date = $eol->format("M Y");

        // If we are within 6 months of End of Life, we should raise a warning report.
        $six_months_from_now = $this->current_date->add(DateInterval::createFromDateString("6 months"));
        if ($eol < $six_months_from_now) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_WARNING,
                [
                    "Your version of MySQL (" . $entity->getVersion() . ") will end support on $eol_date.",
                    "This means it will no longer receiving security patches beyond that date.",
                    "Ensure you have planned how to remove blockers to upgrading to a newer version."
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

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Unsupported-Version';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Unsupported MySQL version';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return 'Spot if your MySQL version is out of support and no longer receiving security updates.';
    }
}
