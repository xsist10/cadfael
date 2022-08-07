CREATE TABLE table_with_large_text_index (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    postal_code VARCHAR(10),
    INDEX idx_name (name),
    INDEX idx_postal_code (postal_code),
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE table_empty (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE table_myisam (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id)
) ENGINE=MyISAM;

CREATE TABLE `table_empty_in_tablespace` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) /*!50100 TABLESPACE `innodb_system` */ ENGINE=InnoDB;


CREATE TABLE `table_with_index_prefix` (
    `id` int NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY idx_name (name(12))
);

CREATE TABLE `table_without_index_prefix` (
    `id` int NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY idx_name (name)
);