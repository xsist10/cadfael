<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Account;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Report;

class LockedAccount implements Check
{
    protected const LOCKED_RESERVED_ACCOUNTS = ['mysql.sys', 'mysql.session', 'mysql.infoschema'];
    public function supports($entity): bool
    {
        return $entity instanceof Account
            && $entity->getUser()->isFleshed();
    }

    public function run($entity): ?Report
    {
        $username = $entity->getUser()->user;
        if ($entity->getUser()->isLocal() && in_array($username, self::LOCKED_RESERVED_ACCOUNTS)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK,
                [ "Some local reserved accounts are expected to be locked." ]
            );
        }

        return new Report(
            $this,
            $entity,
            $entity->getUser()->account_locked ? Report::STATUS_INFO : Report::STATUS_OK
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Locked-Account';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Locked Account';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "Accounts that are locked and may need a cleanup.";
    }
}
