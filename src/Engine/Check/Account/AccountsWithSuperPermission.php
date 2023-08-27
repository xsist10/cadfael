<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Account;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Report;

class AccountsWithSuperPermission implements Check
{
    protected const RESERVED_ACCOUNTS_WITH_SUPER = ['root', 'mysql.session'];

    public function supports($entity): bool
    {
        return $entity instanceof Account
            && $entity->getUser()->isFleshed();
    }

    public function run($entity): ?Report
    {
        // We can exclude some very specific reserved accounts who should normally have this permission
        $username = $entity->getUser()->user;
        if ($entity->getUser()->isLocal() && in_array($username, self::RESERVED_ACCOUNTS_WITH_SUPER)) {
            return new Report(
                $this,
                $entity,
                Report::STATUS_OK,
                [ "Some local reserved accounts are expected to have super permission." ]
            );
        }

        return new Report(
            $this,
            $entity,
            $entity->getUser()->super_priv ? Report::STATUS_CONCERN : Report::STATUS_OK
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Account-with-Super-Permission';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Account with Super Permission';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "Accounts that have super permission assigned and probably don't need it.";
    }
}
