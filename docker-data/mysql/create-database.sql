-- Adminer 4.1.0 MySQL dump

-- SET NAMES utf8;
-- SET time_zone = '+00:00';

DROP TABLE IF EXISTS `bikes`;
CREATE TABLE `bikes` (
  `bikeNum` int(11) UNSIGNED NOT NULL,
  `currentUser` int(11) UNSIGNED DEFAULT NULL,
  `currentStand` int(11) UNSIGNED DEFAULT NULL,
  `currentCode` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`bikeNum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `credit`;
CREATE TABLE `credit` (
  `userId` int(11) UNSIGNED NOT NULL,
  `credit` float(5,2) DEFAULT NULL,
  PRIMARY KEY (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `coupon` varchar(6) NOT NULL,
  `value` float(5,2),
  `status` int(11) UNSIGNED DEFAULT '0',
  UNIQUE KEY `coupon` (`coupon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `notes`;
CREATE TABLE `notes` (
  `noteId` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bikeNum` int(11) UNSIGNED DEFAULT NULL,
  `standId` int(11) UNSIGNED DEFAULT NULL,
  `userId` int(11) UNSIGNED NOT NULL,
  `note` varchar(255),
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`noteId`),
  KEY `bikeNum` (`bikeNum`),
  KEY `standId` (`standId`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `geolocation`;
CREATE TABLE `geolocation` (
  `bikeNum` int(10) unsigned NOT NULL,
  `longitude` double(20,17) NOT NULL,
  `latitude` double(20,17) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `history`;
CREATE TABLE `history` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) UNSIGNED NOT NULL,
  `bikeNum` int(11) UNSIGNED NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `action` enum('RENT','RETURN','REVERT','FORCERENT','FORCERETURN','PHONE_CONFIRM_REQUEST','PHONE_CONFIRMED','EMAIL_CONFIRMED','CREDITCHANGE','CREDIT') NOT NULL,
  `parameter` text NOT NULL,
  `standId` int(11) UNSIGNED DEFAULT NULL,
  `pairActionId` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bikeNum` (`bikeNum`),
  KEY `userId` (`userId`),
  KEY `action` (`action`),
  KEY `standId` (`standId`),
  KEY `pairActionId` (`pairActionId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- THIS TABLE IS MISSED IN CODE, DO WE NEED IT?
DROP TABLE IF EXISTS `pairing`;
CREATE TABLE `pairing` (
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `standid` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `received`;
CREATE TABLE `received` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sms_uuid` varchar(60) NOT NULL,
  `sender` varchar(20) NOT NULL,
  `receive_time` varchar(20) NOT NULL,
  `sms_text` varchar(1024) NOT NULL,
  `IP` varchar(39) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sender` (`sender`),
  KEY `IP` (`IP`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `registration`;
CREATE TABLE `registration` (
  `userId` int(11) UNSIGNED NOT NULL,
  `userKey` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `sent`;
CREATE TABLE `sent` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `number` varchar(20) NOT NULL,
  `text` varchar(1024) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `remember_me_token`;
CREATE TABLE `remember_me_token` (
  `series` varchar(88) NOT NULL,
  `value` varchar(88) NOT NULL,
  `class` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `lastUsed` TIMESTAMP NOT NULL,
  PRIMARY KEY (`series`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `stands`;
CREATE TABLE `stands` (
  `standId` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `standName` varchar(50) NOT NULL,
  `standDescription` varchar(255),
  `standPhoto` varchar(255),
  `serviceTag` int(10),
  `placeName` varchar(50) NOT NULL,
  `longitude` double(20,17),
  `latitude` double(20,17),
  `city` varchar(45) NOT NULL DEFAULT 'Bratislava',
  PRIMARY KEY (`standId`),
  UNIQUE KEY `standName` (`standName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `userSettings`;
CREATE TABLE `userSettings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `userId` INT NOT NULL,
  `settings` JSON NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `userId` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userName` varchar(50) NOT NULL,
  `password` text NOT NULL,
  `mail` varchar(255) NOT NULL,
  `number` varchar(30) NOT NULL,
  `privileges` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `userLimit` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `city` varchar(45) NOT NULL DEFAULT 'Bratisalva',
  `isNumberConfirmed` tinyint(1) NOT NULL DEFAULT '0',
  `registrationDate` datetime DEFAULT NOW(),
  PRIMARY KEY (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- 2014-11-20 11:19:49
