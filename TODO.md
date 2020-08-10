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
    - [ ] Collect mysql.innodb_index_stats metrics
- [ ] Add support for partition detection and reporting
- [ ] Overly permissive permission.
- [ ] At version specific reserved column checks

## Entities

- [x] Add Database element
- [ ] Database vs Schema
- [ ] Permission Entity

## Analysis

- [ ] Add checkers to see if performance_schema metrics are being collected.
