-- Migrate user limits from separate `limits` table to `users` table
ALTER TABLE users ADD COLUMN userLimit INT(11) UNSIGNED NOT NULL DEFAULT 0;
UPDATE users u JOIN limits l ON u.userId = l.userId SET u.userLimit = l.userLimit;
DROP TABLE limits;

