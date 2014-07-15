-- Adminer 4.1.0 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE `WB` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `WB`;

DROP TABLE IF EXISTS `bikes`;
CREATE TABLE `bikes` (
  `bikeNum` int(11) NOT NULL,
  `currentUser` int(11) DEFAULT NULL,
  `currentStand` int(11) DEFAULT NULL,
  `currentCode` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `bikes` (`bikeNum`, `currentUser`, `currentStand`, `currentCode`) VALUES
(1,	NULL,	1,	735);

DROP TABLE IF EXISTS `limits`;
CREATE TABLE `limits` (
  `userId` int(11) NOT NULL,
  `userLimit` int(11) NOT NULL DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `limits` (`userId`, `userLimit`) VALUES
(1,	10);

DROP TABLE IF EXISTS `stands`;
CREATE TABLE `stands` (
  `standId` int(11) NOT NULL,
  `standName` varchar(30) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `stands` (`standId`, `standName`) VALUES
(1,	'MICHAL');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `userId` int(11) NOT NULL,
  `number` varchar(30) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `users` (`userId`, `number`) VALUES
(1,	'421948012705');

-- 2014-07-15 14:16:50
