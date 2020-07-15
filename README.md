# Cadfael

Cadfael is static analysis tool to provide critiquing for databases.

At the moment Cadfael focuses on the MySQL database but the code is structured in a way to allow other databases to be added easily.

## Installation

While this project is in early development, you'll need to specify the non-stable version:


```bash
composer require cadfael/cadfael=^0.1
```

## Usage

```bash
./vendor/bin/cadfael run --host 127.0.0.1 --username root --port 3306 [database_to_scan]
```

**Output**
```
Cadfael CLI Tool

Host: localhost:3306
User: [username]

Attempting to scan schema tests
What is the database password?

+----------------------+------------------------------------------+----------+----------------------------------------------------------------------------------+
| Check                | Entity                                   | Status   | Message                                                                          |
+----------------------+------------------------------------------+----------+----------------------------------------------------------------------------------+
| SaneInnoDbPrimaryKey | table_with_insane_primary_key            | Warning  | In InnoDB tables, the PRIMARY KEY is appended to other indexes.                  |
|                      |                                          |          | If the PRIMARY KEY is big, other indexes will use more space.                    |
|                      |                                          |          | Maybe turn your PRIMARY KEY into UNIQUE and add an auto_increment PRIMARY KEY.   |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/5.7/en/innodb-index-types.html       |
| EmptyTable           | table_with_insane_primary_key            | Warning  | Table contains no records.                                                       |
| RedundantIndexes     | table_with_insane_primary_key            | Concern  | Redundant index full_name (superseded by full_name_height_in_cm).                |
|                      |                                          |          | A redundant index can probably drop it (unless it's a UNIQUE, in which case the  |
|                      |                                          |          | dominant index might be a better candidate for reworking).                       |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/5.7/en/sys-schema-redundant-indexes. |
|                      |                                          |          | html                                                                             |
| ReservedKeywords     | table_with_insane_primary_key.name       | Concern  | `name` is a reserved keyword in MySQL 8.0.                                       |
|                      |                                          |          | Avoid using reserved words as a column name.                                     |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/8.0/en/keywords.html                 |
| ReservedKeywords     | table_with_keyword_columns.some          | Concern  | `some` is a reserved keyword in MySQL 8.0.                                       |
|                      |                                          |          | Avoid using reserved words as a column name.                                     |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/8.0/en/keywords.html                 |
| ReservedKeywords     | table_with_keyword_columns.avg           | Concern  | `avg` is a reserved keyword in MySQL 8.0.                                        |
|                      |                                          |          | Avoid using reserved words as a column name.                                     |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/8.0/en/keywords.html                 |
| SaneAutoIncrement    | table_with_non_primary_autoincrement.aut | Warning  | This field should be set as the primary key.                                     |
|                      | o_incremental                            |          |                                                                                  |
| EmptyTable           | table_with_signed_autoincrement          | Warning  | Table contains no records.                                                       |
| SaneAutoIncrement    | table_with_signed_autoincrement.id       | Warning  | This field should be an unsigned integer type.                                   |
| RedundantIndexes     | table_with_unused_index                  | Concern  | Redundant index some (superseded by some_avg).                                   |
|                      |                                          |          | A redundant index can probably drop it (unless it's a UNIQUE, in which case the  |
|                      |                                          |          | dominant index might be a better candidate for reworking).                       |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/5.7/en/sys-schema-redundant-indexes. |
|                      |                                          |          | html                                                                             |
| ReservedKeywords     | table_with_unused_index.some             | Concern  | `some` is a reserved keyword in MySQL 8.0.                                       |
|                      |                                          |          | Avoid using reserved words as a column name.                                     |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/8.0/en/keywords.html                 |
| ReservedKeywords     | table_with_unused_index.avg              | Concern  | `avg` is a reserved keyword in MySQL 8.0.                                        |
|                      |                                          |          | Avoid using reserved words as a column name.                                     |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/8.0/en/keywords.html                 |
| EmptyTable           | table_with_utf8_encoding                 | Warning  | Table contains no records.                                                       |
| CorrectUtf8Encoding  | table_with_utf8_encoding.utf8_encoding   | Concern  | Character set should be utf8mb2 not utf8.                                        |
|                      |                                          |          | Reference: https://www.eversql.com/mysql-utf8-vs-utf8mb4-whats-the-difference-be |
|                      |                                          |          | tween-utf8-and-utf8mb4/                                                          |
| MustHavePrimaryKey   | table_without_primary_key                | Critical | Table must have a PRIMARY KEY                                                    |
|                      |                                          |          | Reference: https://federico-razzoli.com/why-mysql-tables-need-a-primary-key.     |
|                      |                                          |          | MySQL 8 replication will break if you have InnoDB tables without a PRIMARY KEY.  |
| ReservedKeywords     | table_without_primary_key.name           | Concern  | `name` is a reserved keyword in MySQL 8.0.                                       |
|                      |                                          |          | Avoid using reserved words as a column name.                                     |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/8.0/en/keywords.html                 |
| EmptyTable           | table_without_rows_but_data_free         | Info     | Table is empty but has free space. It is probably used as a some form of queue.  |
| ReservedKeywords     | table_without_rows_but_data_free.name    | Concern  | `name` is a reserved keyword in MySQL 8.0.                                       |
|                      |                                          |          | Avoid using reserved words as a column name.                                     |
|                      |                                          |          | Reference: https://dev.mysql.com/doc/refman/8.0/en/keywords.html                 |
+----------------------+------------------------------------------+----------+----------------------------------------------------------------------------------+
```

## Contributions

This project adopts the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md) for contributions.

Feel free to open an issue if you find any problems or have any suggestions or requests.