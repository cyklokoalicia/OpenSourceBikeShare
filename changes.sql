##### upgrading from v10:43
ALTER TABLE `stands`
ADD `standPhoto` varchar(255) COLLATE 'utf8_general_ci' AFTER `standDescription`,
COMMENT='';
CHANGE `standDescription` `standDescription` varchar(100) COLLATE 'utf8_general_ci' NOT NULL AFTER `standName`,
CHANGE `standPhoto` `standPhoto` varchar(255) COLLATE 'utf8_general_ci' NOT NULL AFTER `standDescription`,
COMMENT='';
UPDATE stands set standPhoto='' where standPhoto=NULL

#
