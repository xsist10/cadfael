<?php

declare(strict_types=1);


namespace Cadfael\Builder\Statement;

use Cadfael\Engine\Entity\Account as AccountEntity;
use Cadfael\Engine\Entity\Account\User;
use SqlFtw\Sql\Dal\User\CreateUserCommand;

class Account
{
    public static function createFromCommand(CreateUserCommand $command): array
    {
        $accounts = [];
        foreach ($command->getUsers() as $create_user) {
            $user = new User(
                $create_user->getUser()->getUser(),
                $create_user->getUser()->getHost(),
            );

            if ($command->getPasswordLockOptions()) {
                foreach ($command->getPasswordLockOptions() as $lock_options) {
                    if ($lock_options->getType()->getValue() === 'ACCOUNT' && $lock_options->getValue() === 'LOCK') {
                        $user->account_locked = true;
                    }
                }
            }

            $accounts[] = AccountEntity::withUser($user);
       }
        return $accounts;
    }
}