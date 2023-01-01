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
        if ($entity->getUser()->authentication_string) {
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
