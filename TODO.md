# TODO

- [x] Add Orchestrator
- [x] Setup workflow to save coverage artifacts
- [x] Add Logger
- [x] Add regular expression support to permission checks (to support `%` and `*` characters).
- [ ] Code currently violates Liskov substitution principle. Clean up inheritance mess.

## Checks

- [x] Add check for out of support database version
- [ ] Add check for database version CVEs
- [x] Table Engine recommendation
- [ ] Table Growth detection
    - [ ] Try determine creation values
- [ ] Table access meta
    - [x] Collect `mysql.innodb_index_stats` metrics
- [ ] Add support for partition detection and reporting
- [ ] At version specific reserved column checks
- [ ] Map permissions to queries to determine which permissions are not being used.

## Entities

- [x] Add Schema element
- [x] Split Database vs Schema
- [ ] Permission Entity
- [x] Add Index size calculation

## Analysis

- [ ] Add checkers to see if performance_schema metrics are being collected.
- [ ] Linter for table design