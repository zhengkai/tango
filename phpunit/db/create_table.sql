-- MySQL dump 10.13  Distrib 5.5.41, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: tango_test
-- ------------------------------------------------------
-- Server version	5.5.41-0ubuntu0.14.04.1-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `tango_test`
--

/*!40000 DROP DATABASE IF EXISTS `tango_test`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `tango_test` /*!40100 DEFAULT CHARACTER SET ucs2 */;

USE `tango_test`;

--
-- Table structure for table `enum_test_a`
--

DROP TABLE IF EXISTS `enum_test_a`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enum_test_a` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `row_a` char(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game` (`row_a`)
) ENGINE=MyISAM DEFAULT CHARSET=ucs2 DELAY_KEY_WRITE=1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enum_test_a`
--

LOCK TABLES `enum_test_a` WRITE;
/*!40000 ALTER TABLE `enum_test_a` DISABLE KEYS */;
/*!40000 ALTER TABLE `enum_test_a` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enum_test_b`
--

DROP TABLE IF EXISTS `enum_test_b`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enum_test_b` (
  `uid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `row_a` char(50) NOT NULL,
  `row_b` int(10) unsigned NOT NULL,
  `row_c` char(50) NOT NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `game` (`row_a`)
) ENGINE=MyISAM DEFAULT CHARSET=ucs2 DELAY_KEY_WRITE=1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enum_test_b`
--

LOCK TABLES `enum_test_b` WRITE;
/*!40000 ALTER TABLE `enum_test_b` DISABLE KEYS */;
/*!40000 ALTER TABLE `enum_test_b` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enum_test_c`
--

DROP TABLE IF EXISTS `enum_test_c`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enum_test_c` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash_a` binary(20) NOT NULL,
  `row_a` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash_a`)
) ENGINE=MyISAM DEFAULT CHARSET=ucs2 DELAY_KEY_WRITE=1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enum_test_c`
--

LOCK TABLES `enum_test_c` WRITE;
/*!40000 ALTER TABLE `enum_test_c` DISABLE KEYS */;
/*!40000 ALTER TABLE `enum_test_c` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enum_test_d`
--

DROP TABLE IF EXISTS `enum_test_d`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enum_test_d` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` binary(16) NOT NULL,
  `row_a` text NOT NULL,
  `row_b` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=MyISAM DEFAULT CHARSET=ucs2 DELAY_KEY_WRITE=1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enum_test_d`
--

LOCK TABLES `enum_test_d` WRITE;
/*!40000 ALTER TABLE `enum_test_d` DISABLE KEYS */;
/*!40000 ALTER TABLE `enum_test_d` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pdo_test`
--

DROP TABLE IF EXISTS `pdo_test`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pdo_test` (
  `test_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_ban` enum('N','Y') CHARACTER SET ascii NOT NULL DEFAULT 'N',
  `name` char(30) NOT NULL DEFAULT '',
  `date_create` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`test_id`)
) ENGINE=MyISAM DEFAULT CHARSET=ucs2;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pdo_test`
--

LOCK TABLES `pdo_test` WRITE;
/*!40000 ALTER TABLE `pdo_test` DISABLE KEYS */;
/*!40000 ALTER TABLE `pdo_test` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2015-02-15 17:05:18
