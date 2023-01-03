<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Entity;

use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Entity\Database;
use Cadfael\Tests\Engine\BaseTest;

class AccountTest extends BaseTest
{
    protected Account $account;

    protected function setUp(): void
    {
        $this->account = $this->createAccount("root", "localhost");
        $this->account->setDatabase(new Database(null));
    }

    public function test__isVirtual()
    {
        $this->assertFalse($this->account->isVirtual(), "Verify that the account is correctly identified as not virtual.");;
    }

    public function test____toString()
    {
        $this->assertEquals((string)$this->account, "root@localhost", "Ensure the toString function properly formats the result.");
    }
}
