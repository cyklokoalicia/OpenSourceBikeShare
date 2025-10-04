# Migration Guide from version 1.0.0
## DB Schema Changes

```sql
ALTER TABLE `users` ADD `isNumberConfirmed` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `users` ADD `registrationDate` DATETIME DEFAULT NOW();
UPDATE users u
SET u.registrationDate = (
    SELECT MIN(h.time)
    FROM history h
    WHERE h.userId = u.userId
);

ALTER TABLE `history` MODIFY `action` enum('RENT','RETURN','REVERT','FORCERENT','FORCERETURN','PHONE_CONFIRM_REQUEST','PHONE_CONFIRMED','EMAIL_CONFIRMED','CREDITCHANGE','CREDIT', 'CHANGECODE') NOT NULL,

ALTER TABLE `users` ADD COLUMN userLimit INT(11) UNSIGNED NOT NULL DEFAULT 0;
UPDATE users u JOIN limits l ON u.userId = l.userId SET u.userLimit = l.userLimit;
RENAME TABLE limits TO limits_delete;
#DROP TABLE limits_delete;

CREATE TABLE `userSettings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `userId` INT NOT NULL,
    `settings` JSON NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

```