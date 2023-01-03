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
    public function providerAccountData() {
        return [
            // Passwordless account with local access
            [
                'localhost',
                '5.5.3',
                'not_empty',
                null,
                Report::STATUS_OK
            ],
            [
                'localhost',
                '5.5.3',
                null,
                null,
                Report::STATUS_WARNING
            ],
            [
                'localhost',
                '5.6.0',
                'mysql_native_password',
                null,
                Report::STATUS_WARNING
            ],
            [
                'localhost',
                '5.7.2',
                null,
                null,
                Report::STATUS_WARNING
            ],
            [
                'localhost',
                '5.7.2',
                'mysql_native_password',
                null,
                Report::STATUS_WARNING
            ],
            [
                'localhost',
                '8.1',
                null,
                null,
                Report::STATUS_WARNING
            ],
            [
                'localhost',
                '8.1',
                'mysql_native_password',
                null,
                Report::STATUS_WARNING
            ],
            // Passwordless account with non-local access
            [
                '%',
                '5.5.3',
                'not_empty',
                null,
                Report::STATUS_OK
            ],
            [
                '%',
                '5.5.3',
                null,
                null,
                Report::STATUS_CRITICAL
            ],
            [
                '%',
                '5.6.0',
                'mysql_native_password',
                null,
                Report::STATUS_CRITICAL
            ],
            [
                '%',
                '5.7.2',
                null,
                null,
                Report::STATUS_CRITICAL
            ],
            [
                '%',
                '5.7.2',
                'mysql_native_password',
                null,
                Report::STATUS_CRITICAL
            ],
            [
                '%',
                '8.1',
                null,
                null,
                Report::STATUS_CRITICAL
            ],
            [
                '%',
                '8.1',
                'mysql_native_password',
                null,
                Report::STATUS_CRITICAL
            ],
            // Passworded account
            [
                'localhost',
                '5.5.3',
                'not_empty',
                'randomJibberish',
                Report::STATUS_OK
            ],
            [
                'localhost',
                '5.5.3',
                null,
                'randomJibberish',
                Report::STATUS_OK
            ],
            [
                'localhost',
                '5.6.0',
                'mysql_native_password',
                'randomJibberish',
                Report::STATUS_OK
            ],
            [
                'localhost',
                '5.7.2',
                null,
                'randomJibberish',
                Report::STATUS_OK
            ],
            [
                'localhost',
                '5.7.2',
                'mysql_native_password',
                'randomJibberish',
                Report::STATUS_OK
            ],
            [
                'localhost',
                '8.1',
                null,
                'randomJibberish',
                Report::STATUS_OK
            ],
            [
                'localhost',
                '8.1',
                'mysql_native_password',
                'randomJibberish',
                Report::STATUS_OK
            ],
        ];
    }

    /**
     * @dataProvider providerAccountData
     */
    public function testRun($host, $version, $plugin, $password, $status)
    {
        $check = new PasswordlessAccount();

        $account = Account::withUser(new User("test", $host, plugin: $plugin, authentication_string: $password));
        $account->setDatabase($this->createDatabase([ 'version' => $version ]));

        $this->assertTrue(
            $check->supports($account),
            "Ensure that we care about all accounts."
        );

        $this->assertEquals(
            $status,
            $check->run($account)->getStatus(),
            "test@$host on MySQL $version with plugin:$plugin and authentication_string:$password tests correctly: $status."
        );
    }
}
