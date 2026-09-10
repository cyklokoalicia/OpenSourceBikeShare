-- After legacy pair normalization. New writes may start before this phase
-- under the coordinated writer's locks and fixed history-ID cutover boundary.
ALTER TABLE `history`
    ADD UNIQUE KEY `uniq_history_pair` (`pairActionId`),
    ADD CONSTRAINT `fk_history_pair` FOREIGN KEY (`pairActionId`) REFERENCES `history` (`id`),
    ADD CONSTRAINT `chk_history_pair_direction` CHECK (
        `pairActionId` IS NULL OR `action` IN ('RETURN','FORCERETURN','REVERT')
    );
