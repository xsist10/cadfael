CREATE DATABASE IF NOT EXISTS test;
USE test;

DROP TABLE IF EXISTS `table_with_large_text_index`;
CREATE TABLE `table_with_large_text_index` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    postal_code VARCHAR(10),
    INDEX idx_name (name),
    INDEX idx_postal_code (postal_code),
    PRIMARY KEY (id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `table_empty`;
CREATE TABLE `table_empty` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `table_myisam`;
CREATE TABLE `table_myisam` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `table_empty_in_tablespace`;
CREATE TABLE `table_empty_in_tablespace` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) /*!50100 TABLESPACE `innodb_system` */ ENGINE=InnoDB;

DROP TABLE IF EXISTS `table_with_index_prefix`;
CREATE TABLE `table_with_index_prefix` (
    `id` int NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY idx_name (name(12))
);

DROP TABLE IF EXISTS `table_without_index_prefix`;
CREATE TABLE `table_without_index_prefix` (
    `id` int NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY idx_name (name)
);

DROP TABLE IF EXISTS `table_with_high_cardinality_string_column`;
CREATE TABLE `table_with_high_cardinality_string_column` (
     `id` int NOT NULL AUTO_INCREMENT,
     name VARCHAR(255) NOT NULL,
     PRIMARY KEY (`id`),
     KEY idx_name (name)
);

# Populate `table_with_high_cardinality_string_column` with a bunch of entries
SET @@cte_max_recursion_depth  = 5000;
INSERT INTO `table_with_high_cardinality_string_column` (`id`, `name`)
WITH RECURSIVE cte AS
   (
       SELECT 1 AS i, 'value1' AS value
       UNION ALL
       SELECT i+1, 'value1' AS value
       FROM cte
       WHERE i < 1000
   )
SELECT i, value
FROM cte;

INSERT INTO `table_with_high_cardinality_string_column` (`id`, `name`)
WITH RECURSIVE cte AS
   (
       SELECT 1001 AS i, 'value2' AS value
       UNION ALL
       SELECT i+1, 'value2' AS value
       FROM cte
       WHERE i < 2000
   )
SELECT i, value
FROM cte;

# Create a passwordless account
CREATE USER IF NOT EXISTS 'localhost_passwordless_user'@'localhost';

# Create a locked account
CREATE USER IF NOT EXISTS 'locked_account'@'localhost' IDENTIFIED BY RANDOM PASSWORD ACCOUNT LOCK;

# Create some test query data
SELECT a.*, b.*
FROM table_with_high_cardinality_string_column AS a
JOIN table_with_high_cardinality_string_column AS b ON (a.id = b.id)
WHERE a.name = 'value1';

SELECT a.*, b.id AS bid
FROM table_with_high_cardinality_string_column AS a
JOIN (SELECT id FROM table_with_high_cardinality_string_column) AS b ON (a.id = b.id)
WHERE a.name = 'value1';