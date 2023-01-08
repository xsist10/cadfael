<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Account;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Report;

class OutdatedAuthenticationMethod implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Account;
    }

    public function run($entity): ?Report
    {
        $version = $entity->getDatabase()->getVersion();

        // If we're running MySQL 5.6.* to <= 8.0.0, we flag if the authentication method is mysql_old_password
        if (version_compare($version, '8.0.0', '<')
            && version_compare($version, '5.6', '>=')
            && (
                // See https://dev.mysql.com/doc/refman/5.7/en/account-upgrades.html
                ($entity->getUser()->plugin === 'mysql_old_password')
                || (!$entity->getUser()->plugin && strlen($this->getUser()->authentication_string) == 16)
            )
        ) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_WARNING,
                [ "For MySQL 5.6+, accounts that use mysql_old_password should upgrade to mysql_native_password." ]
            );
        }

        // If we're running MySQL 5.5.*, we only flag an issue if the plugin field is NULL or empty
        if (version_compare($version, '8.0', '>=')
            && $entity->getUser()->plugin === 'mysql_native_password'
        ) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_WARNING,
                [ "For MySQL 8.0+, accounts that use mysql_native_password should upgrade to caching_sha2_password." ]
            );
        }

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
        return 'https://github.com/xsist10/cadfael/wiki/Outdated-Authentication-Methods';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Outdated Authentication Methods';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "Accounts that use weaker or deprecated authentication mechanism are potential security issues.";
    }
}
