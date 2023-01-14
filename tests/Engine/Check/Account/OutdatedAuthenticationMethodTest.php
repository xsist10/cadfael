<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Account;

use Cadfael\Engine\Check\Account\OutdatedAuthenticationMethod;
use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Entity\Account\User;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;

class OutdatedAuthenticationMethodTest extends BaseTest
{
    public function providerAccountData() {
        return [
            // MySQL >= 5.6 and < 8.0
            [
                '5.6.0',
                'mysql_native_password',
                'some_password',
                Report::STATUS_OK
            ],
            [
                '5.6.3',
                '',
                'this_string_is16',
                Report::STATUS_WARNING
            ],
            [
                '5.7.3',
                null,
                'this_string_is16',
                Report::STATUS_WARNING
            ],
            [
                '5.6.0',
                'mysql_old_password',
                'some_password',
                Report::STATUS_WARNING
            ],

            // MySQL >= 8.0
            [
                '8.0.1',
                'caching_sha2_password',
                'some_password',
                Report::STATUS_OK
            ],
            [
                '8.0.1',
                'mysql_native_password',
                'some_password',
                Report::STATUS_CONCERN
            ]
        ];
    }

    /**
     * @dataProvider providerAccountData
     */
    public function testRun($version, $plugin, $password, $status)
    {
        $check = new OutdatedAuthenticationMethod();

        $account = Account::withUser(new User("test", 'localhost', plugin: $plugin, authentication_string: $password));
        $account->setDatabase($this->createDatabase([ 'version' => $version ]));

        $this->assertTrue(
            $check->supports($account),
            "Ensure that we care about all accounts."
        );

        $this->assertEquals(
            $status,
            $check->run($account)->getStatus(),
            "MySQL $version with plugin:$plugin and authentication_string:$password tests correctly: $status."
        );
    }
}
