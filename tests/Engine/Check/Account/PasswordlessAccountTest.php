<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Account;

use Cadfael\Engine\Check\Account\PasswordlessAccount;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Entity\Account\User;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class PasswordlessAccountTest extends BaseTest
{
    private array $accounts;

    public function setUp(): void
    {
        $passwordless_account = $this->createAccount('passwordless_account', 'localhost');
        $passwordless_account_non_local = $this->createAccount('passwordless_account', '%');
        $passworded_account = Account::withUser(new User('passworded_account', 'localhost', authentication_string: "randomJibberish"));

        $this->accounts = [
            $passwordless_account,
            $passwordless_account_non_local,
            $passworded_account
        ];
    }

    public function testSupports()
    {
        $check = new PasswordlessAccount();

        foreach ($this->accounts as $account) {
            $this->assertTrue(
                $check->supports($account),
                "Ensure that we care about all accounts."
            );
        }
    }

    public function testRun()
    {
        $check = new PasswordlessAccount();

        $account = $this->accounts[0];
        $this->assertEquals(
            Report::STATUS_WARNING,
            $check->run($account)->getStatus(),
            $account->getName() . "@" . $account->getHost() . " has no password set for a localhost account."
        );

        $account = $this->accounts[1];
        $this->assertEquals(
            Report::STATUS_CRITICAL,
            $check->run($account)->getStatus(),
            $account->getName() . "@" . $account->getHost() . " has no password set for a non-localhost account."
        );

        $account = $this->accounts[2];
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($account)->getStatus(),
            $account->getName() . "@" . $account->getHost() . " has a password set."
        );
    }
}
