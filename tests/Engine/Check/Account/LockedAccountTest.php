<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Account;

use Cadfael\Engine\Check\Account\LockedAccount;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Entity\Account\User;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class LockedAccountTest extends BaseTest
{
    public function testRun()
    {
        $check = new LockedAccount();

        $account = Account::withUser(new User("test", "localhost", account_locked: false));

        $this->assertTrue(
            $check->supports($account),
            "Ensure that we care about all accounts."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($account)->getStatus(),
            "Unlocked account is fine."
        );

        $account = Account::withUser(new User("test", "localhost", account_locked: true));

        $this->assertTrue(
            $check->supports($account),
            "Ensure that we care about all accounts."
        );

        $this->assertEquals(
            Report::STATUS_INFO,
            $check->run($account)->getStatus(),
            "Locked account is informed on."
        );
    }
}
