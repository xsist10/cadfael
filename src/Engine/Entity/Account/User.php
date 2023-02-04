<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity\Account;

/**
 * Class User (readonly)
 *
 * Stores a representation of mysql.user
 * @package Cadfael\Engine\Entity\Account
 * @codeCoverageIgnore
 */
class User
{
    public function __construct(
        public string $user,
        public string $host,
        public bool $select_priv = false,
        public bool $insert_priv = false,
        public bool $update_priv = false,
        public bool $delete_priv = false,
        public bool $create_priv = false,
        public bool $drop_priv = false,
        public bool $reload_priv = false,
        public bool $shutdown_priv = false,
        public bool $process_priv = false,
        public bool $file_priv = false,
        public bool $grant_priv = false,
        public bool $references_priv = false,
        public bool $index_priv = false,
        public bool $alter_priv = false,
        public bool $show_db_priv = false,
        public bool $super_priv = false,
        public bool $create_tmp_table_priv = false,
        public bool $lock_tables_priv = false,
        public bool $execute_priv = false,
        public bool $repl_slave_priv = false,
        public bool $repl_client_priv = false,
        public bool $create_view_priv = false,
        public bool $show_view_priv = false,
        public bool $create_routine_priv = false,
        public bool $alter_routine_priv = false,
        public bool $create_user_priv = false,
        public bool $event_priv = false,
        public bool $trigger_priv = false,
        public bool $create_tablespace_priv = false,
        public string $ssl_type = '',
        public string|null $ssl_cipher = null,
        public string|null $x509_issuer = null,
        public string|null $x509_subject = null,
        public int $max_questions = 0,
        public int $max_updates = 0,
        public int $max_connections = 0,
        public int $max_user_connections = 0,
        public string|null $plugin = 'caching_sha2_password',
        public string|null $authentication_string = null,
        public bool|null $password_expired = false,
        public int|null $password_last_changed = null,
        public int|null $password_lifetime = null,
        public bool|null $account_locked = false,
        public bool|null $create_role_priv = false,
        public bool|null $drop_role_priv = false,
        public int|null $password_reuse_history = null,
        public int|null $password_reuse_time = null,
        public bool|null $password_require_current = null,
        public array|null $user_attributes = null
    ) {
    }

    /**
     * @param array<string> $payload This is a query from mysql.user
     * @return User
     */
    public static function createFromUser(array $payload): User
    {
        return new User(
            $payload['User'],
            $payload['Host'],
            $payload['Select_priv'] === 'Y',
            $payload['Insert_priv'] === 'Y',
            $payload['Update_priv'] === 'Y',
            $payload['Delete_priv'] === 'Y',
            $payload['Create_priv'] === 'Y',
            $payload['Drop_priv'] === 'Y',
            $payload['Reload_priv'] === 'Y',
            $payload['Shutdown_priv'] === 'Y',
            $payload['Process_priv'] === 'Y',
            $payload['File_priv'] === 'Y',
            $payload['Grant_priv'] === 'Y',
            $payload['References_priv'] === 'Y',
            $payload['Index_priv'] === 'Y',
            $payload['Alter_priv'] === 'Y',
            $payload['Show_db_priv'] === 'Y',
            $payload['Super_priv'] === 'Y',
            $payload['Create_tmp_table_priv'] === 'Y',
            $payload['Lock_tables_priv'] === 'Y',
            $payload['Execute_priv'] === 'Y',
            $payload['Repl_slave_priv'] === 'Y',
            $payload['Repl_client_priv'] === 'Y',
            $payload['Create_view_priv'] === 'Y',
            $payload['Show_view_priv'] === 'Y',
            $payload['Create_routine_priv'] === 'Y',
            $payload['Alter_routine_priv'] === 'Y',
            $payload['Create_user_priv'] === 'Y',
            $payload['Event_priv'] === 'Y',
            $payload['Trigger_priv'] === 'Y',
            $payload['Create_tablespace_priv'] === 'Y',
            $payload['ssl_type'],
            $payload['ssl_cipher'],
            $payload['x509_issuer'],
            $payload['x509_subject'],
            (int)$payload['max_questions'],
            (int)$payload['max_updates'],
            (int)$payload['max_connections'],
            (int)$payload['max_user_connections'],
            $payload['plugin'],
            $payload['authentication_string'],
            $payload['password_expired'] === 'Y',
            (int)$payload['password_last_changed'],
            (int)$payload['password_lifetime'],
            $payload['account_locked'] === 'Y',
            $payload['Create_role_priv'] === 'Y',
            $payload['Drop_role_priv'] === 'Y',
            (int)$payload['Password_reuse_history'],
            (int)$payload['Password_reuse_time'],
            $payload['Password_require_current'] === 'Y',
            json_decode($payload['User_attributes'] ?? '{}', true)
        );
    }
}
