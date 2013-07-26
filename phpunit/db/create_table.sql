CREATE DATABASE /*!32312 IF NOT EXISTS*/ `tango_test` /*!40100 DEFAULT CHARACTER SET ucs2 COLLATE ucs2_general_ci */;

USE `tango_test`;

CREATE TABLE IF NOT EXISTS `pdo_test` (
  `test_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_ban` enum('N','Y') CHARACTER SET ascii NOT NULL DEFAULT 'N',
  `name` char(30) NOT NULL DEFAULT '',
  `date_create` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`test_id`)
) ENGINE=MyISAM DEFAULT CHARSET=ucs2 AUTO_INCREMENT=1;
