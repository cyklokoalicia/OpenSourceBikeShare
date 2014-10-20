##### upgrading from v7.10 0:44
DROP TABLE IF EXISTS `credit`;
CREATE TABLE `credit` (
  `userId` int(11) NOT NULL,
  `credit` float(5,2),
  PRIMARY KEY (`userId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

