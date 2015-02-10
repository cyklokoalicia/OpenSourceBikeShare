##### upgrading from v7.10 0:44
DROP TABLE IF EXISTS `credit`;
CREATE TABLE `credit` (
  `userId` int(11) NOT NULL,
  `credit` float(5,2),
  PRIMARY KEY (`userId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

##### upgrading after 2014-12-16
DROP TABLE IF EXISTS `notes`;
CREATE TABLE `notes` (
  `bikeNum` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `note` varchar(100),
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
ALTER TABLE `bikes` DROP `note`;