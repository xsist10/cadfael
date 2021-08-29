# Cadfael

![Build](https://github.com/xsist10/cadfael/workflows/build/badge.svg?branch=master)

Cadfael is static analysis tool to provide critiquing for MySQL databases.

## Installation

There are a couple options for installation depending on your personal preference.

### Phar

You can grab the phar file from the [most recent release](https://github.com/xsist10/cadfael/releases).
This ensures you won't have any dependency conflicts.

### Global

If you'd like Cadfael available anywhere on your system, you can install it globally.

```bash
composer global require cadfael/cadfael
```

Ensure that your global composer vendor bin folder is set in your path. You may need to add this to your `.bashrc` file.

```bash
export PATH=$PATH:~/.config/composer/vendor/bin
```

### Local

If you want to use it within a specific project you can install it with:

```bash
composer require cadfael/cadfael
```

The path to the executable will be in `./vendor/bin/`.

## Usage

```bash
cadfael run --host 127.0.0.1 --username root --port 3306 [database_to_scan]
```

### Advanced Usage

You can also include the `--performance_schema` flag if you wish to run checks against the [performance_schema](https://dev.mysql.com/doc/refman/8.0/en/performance-schema.html) schema which collect analytics about your server since the last time it was restarted. This is particularly useful if you want to see how your database is being used and detect issues related to queries, access of tables and heavy or badly optimized queries.

For meaningful results you *should* run this against the database that is being used in production otherwise you'll only be checking against the metrics collected in your development environment.
**BUT FIRST** always speak to your DBA/Security people first before run random tools from the internet against your production database.

### Output
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

## Take it for a spin

Try testing Cadfael on a few open data sources

```
+-----------------------------+------+-----------+----------+--------------------------+
| Host                        | Port | Username  | Password | Schema                   |
+-----------------------------+------+-----------+----------+--------------------------+
| ensembldb.ensembl.org       | 5306 | anonymous |          | homo_sapiens_core_103_38 |
| mysql-rfam-public.ebi.ac.uk | 4497 | rfamro    |          | Rfam                     |
| mysql-db.1000genomes.org    | 4272 | anonymous |          | homo_sapiens_core_73_37  |
+-----------------------------+------+-----------+----------+--------------------------+
```

## Contributions

This project adopts the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md) for contributions.

Feel free to open an issue if you find any problems or have any suggestions or requests.