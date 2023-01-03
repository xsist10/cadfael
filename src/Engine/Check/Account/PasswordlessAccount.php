<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Check\Account;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Report;

class PasswordlessAccount implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Account;
    }

    public function run($entity): ?Report
    {
        $pass = !is_null($entity->getUser()->authentication_string)
            && $entity->getUser()->authentication_string;

        $version = $entity->getDatabase()->getVersion();

        // If we're running MySQL 5.6.*, we only flag an issue if the plugin field is mysql_native_password
        $pass |= (
            version_compare($version, '5.7', '<')
            && version_compare($version, '5.6', '>=')
            && $entity->getUser()->plugin !== 'mysql_native_password'
        );

        // If we're running MySQL 5.5.*, we only flag an issue if the plugin field is NULL or empty
        $pass |= (
            version_compare($version, '5.6', '<')
            && version_compare($version, '5.5', '>=')
            && $entity->getUser()->plugin
        );

        if ($pass) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK
            );
        }

        $status = Report::STATUS_WARNING;
        $messages = [
            "Passwordless account detected.",
        ];

        // Can this user be accessed from outside the server localhost?
        if ($entity->getUser()->host !== 'localhost' && $entity->getUser()->host !== '127.0.0.1') {
            $status = Report::STATUS_CRITICAL;
            $messages[] = "Account has access from outside the server.";
        }

        return new Report(
            $this,
            $entity,
            $status,
            $messages
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Passwordless-Account';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Passwordless Account';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "Accounts that don't have a password set could be abused.";
    }
}
