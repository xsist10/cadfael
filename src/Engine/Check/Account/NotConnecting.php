<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Account;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Report;

class NotConnecting implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Account;
    }

    public function run($entity): ?Report
    {
        if ($entity->getUser()->isReservedAccount()) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK,
                [ "Reserved accounts are exempt from this check." ]
            );
        }

        if ($entity->getTotalConnections()) {
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
                "This account has not made any connections since the server last restarted.",
            ]
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Not-Connecting';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Accounts not being used';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "Accounts that haven't made a connection since the server restarted.";
    }
}
