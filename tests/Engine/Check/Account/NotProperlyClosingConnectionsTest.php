<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Account;

use Cadfael\Engine\Check\Account\NotProperlyClosingConnections;
use Cadfael\Engine\Entity\Account\NotClosedProperly;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class NotProperlyClosingConnectionsTest extends BaseTest
{
    private array $accounts;

    public function setUp(): void
    {
        $not_closed_properly_account = $this->createAccount('not_closed_properly', 'localhost');
        $not_closed_properly_account->setAccountNotClosedProperly(NotClosedProperly::createFromPerformanceSchema([
            'not_closed'      => 1,
            'not_closed_perc' => 5
        ]));

        $closed_properly_account = $this->createAccount('closed_properly_account', 'localhost');
        $closed_properly_account->setAccountNotClosedProperly(NotClosedProperly::createFromPerformanceSchema([
            'not_closed'      => 0,
            'not_closed_perc' => 0
        ]));

        $this->accounts = [
            $not_closed_properly_account,
            $closed_properly_account
        ];
    }

    public function testSupports()
    {
        $check = new NotProperlyClosingConnections();

        foreach ($this->accounts as $account) {
            $this->assertTrue(
                $check->supports($account),
                "Ensure that we care about all accounts."
            );
        }
    }

    public function testRun()
    {
        $check = new NotProperlyClosingConnections();

        $account = $this->accounts[0];
        $this->assertEquals(
            Report::STATUS_WARNING,
            $check->run($account)->getStatus(),
            $account->getName() . "@" . $account->getHost() . " does not close connections properly."
        );

        $account = $this->accounts[1];
        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($account)->getStatus(),
            $account->getName() . "@" . $account->getHost() . " does close connections properly."
        );
    }
}
