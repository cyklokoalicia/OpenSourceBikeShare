CREATE TABLE `userSettings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `userId` INT NOT NULL,
  `settings` JSON NOT NULL,
  PRIMARY KEY (`id`)
);
