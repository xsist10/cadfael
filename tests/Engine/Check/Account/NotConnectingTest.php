<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Account;

use Cadfael\Engine\Check\Account\NotConnecting;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class NotConnectingTest extends BaseTest
{
    private array $accounts;

    public function setUp(): void
    {
        $no_connection_account = $this->createAccount('no_connections', 'localhost');
        $no_connection_account->setTotalConnections(0);

        $many_connection_account = $this->createAccount('many_connections', 'localhost');
        $many_connection_account->setTotalConnections(100);

        $this->accounts = [
            $no_connection_account,
            $many_connection_account
        ];
    }

    public function testSupports()
    {
        $check = new NotConnecting();

        foreach ($this->accounts as $account) {
            $this->assertTrue(
                $check->supports($account),
                "Ensure that we care about all accounts."
            );
        }
    }

    public function testRun()
    {
        $check = new NotConnecting();

        $account = $this->accounts[0];
        $this->assertEquals(
            Report::STATUS_CONCERN,
            $check->run($account)->getStatus(),
            $account->getName() . "@" . $account->getHost() . " has not connected this session."
        );

        $account = $this->accounts[1];
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($account)->getStatus(),
            $account->getName() . "@" . $account->getHost() . " has connected this session."
        );
    }
}
