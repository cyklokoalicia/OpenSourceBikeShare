-- Adminer 4.1.0 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';

DROP TABLE IF EXISTS `bikes`;
CREATE TABLE `bikes` (
  `bikeNum` int(11) NOT NULL,
  `currentUser` int(11) DEFAULT NULL,
  `currentStand` int(11) DEFAULT NULL,
  `currentCode` int(11) NOT NULL,
  `note` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`bikeNum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `credit`;
CREATE TABLE `credit` (
  `userId` int(11) NOT NULL,
  `credit` float(5,2) DEFAULT NULL,
  PRIMARY KEY (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `notes`;
CREATE TABLE `notes` (
  `bikeNum` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `note` varchar(100),
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted` timestamp NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `geolocation`;
CREATE TABLE `geolocation` (
  `userId` int(10) unsigned NOT NULL,
  `longitude` double(20,17) NOT NULL,
  `latitude` double(20,17) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `history`;
CREATE TABLE `history` (
  `userId` int(11) NOT NULL,
  `bikeNum` int(11) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `action` varchar(20) NOT NULL,
  `parameter` text NOT NULL,
  `standId` int(11) DEFAULT NULL,
  `pairAction` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `limits`;
CREATE TABLE `limits` (
  `userId` int(11) NOT NULL AUTO_INCREMENT,
  `userLimit` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `pairing`;
CREATE TABLE `pairing` (
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `standid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `received`;
CREATE TABLE `received` (
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sms_uuid` varchar(60) NOT NULL,
  `sender` varchar(20) NOT NULL,
  `receive_time` varchar(20) NOT NULL,
  `sms_text` varchar(200) NOT NULL,
  `IP` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `registration`;
CREATE TABLE `registration` (
  `userId` int(11) NOT NULL,
  `userKey` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `sent`;
CREATE TABLE `sent` (
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `number` varchar(20) NOT NULL,
  `text` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `userId` int(10) unsigned NOT NULL,
  `sessionId` varchar(256) CHARACTER SET latin1 NOT NULL,
  `timeStamp` varchar(256) CHARACTER SET latin1 NOT NULL,
  UNIQUE KEY `userId` (`userId`),
  KEY `sessionId` (`sessionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `stands`;
CREATE TABLE `stands` (
  `standId` int(11) NOT NULL AUTO_INCREMENT,
  `standName` varchar(50) NOT NULL,
  `standDescription` varchar(255) DEFAULT NULL,
  `standPhoto` varchar(255) DEFAULT NULL,
  `serviceTag` int(10) NOT NULL,
  `placeName` varchar(50) NOT NULL,
  `longitude` double(20,17) NOT NULL,
  `latitude` double(20,17) NOT NULL,
  PRIMARY KEY (`standId`),
  UNIQUE KEY `standName` (`standName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `userId` int(11) NOT NULL AUTO_INCREMENT,
  `userName` varchar(50) NOT NULL,
  `password` text NOT NULL,
  `mail` varchar(30) NOT NULL,
  `number` varchar(30) NOT NULL,
  `privileges` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- 2014-11-20 11:19:49
