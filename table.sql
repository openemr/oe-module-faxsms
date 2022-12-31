SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE IF NOT EXISTS `module_faxsms_credentials`
(
    `id`          int(10) UNSIGNED NOT NULL,
    `auth_user`   int(10) UNSIGNED DEFAULT '0',
    `vendor`      varchar(60)      DEFAULT NULL,
    `credentials` mediumblob       NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vendor` (`vendor`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8 COMMENT ='Vendor credentials for Fax/SMS';
