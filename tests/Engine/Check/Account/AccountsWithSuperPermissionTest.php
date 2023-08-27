<?php

declare(strict_types=1);


namespace Cadfael\Tests\Engine\Check\Account;

use Cadfael\Engine\Check\Account\AccountsWithSuperPermission;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Entity\Account\User;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class AccountsWithSuperPermissionTest extends BaseTest
{
    public function providerAccountData() {
        return [
            [
                Account::withUser(new User('blah', 'localhost')),
                false,
                null
            ],
            [
                Account::withUser(new User('root', 'localhost', super_priv: true, is_fleshed: true)),
                true,
                Report::STATUS_OK
            ],
            [
                Account::withUser(new User('root', '%', super_priv: true, is_fleshed: true)),
                true,
                Report::STATUS_CONCERN
            ],
            [
                Account::withUser(new User('mysql.session', 'localhost', is_fleshed: true)),
                true,
                Report::STATUS_OK
            ],
            [
                Account::withUser(new User('mysql.session', '%', is_fleshed: true)),
                true,
                Report::STATUS_OK
            ],
            [
                Account::withUser(new User('super_user', 'localhost', super_priv: true, is_fleshed: true)),
                true,
                Report::STATUS_CONCERN
            ],
            [
                Account::withUser(new User('non_super_user', 'localhost', is_fleshed: true)),
                true,
                Report::STATUS_OK
            ],
        ];
    }

    /**
     * @dataProvider providerAccountData
     */
    public function testRun($account, $isSupported, $status)
    {
        $check = new AccountsWithSuperPermission();

        $this->assertEquals(
            $isSupported,
            $check->supports($account),
            "Ensure that we care about only relevant accounts accounts."
        );

        if ($status) {
            $this->assertEquals(
                $status,
                $check->run($account)->getStatus(),
                "Correctly identify issues with super permission."
            );
        }
    }
}
