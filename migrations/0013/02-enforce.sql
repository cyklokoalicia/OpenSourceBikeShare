-- Post-normalization phase ONLY. Do not run on unreviewed legacy pairs.
-- Run under a rental-write pause after archiving/normalizing the old pairActionId.
ALTER TABLE `history`
    ADD UNIQUE KEY `uniq_history_pair` (`pairActionId`),
    ADD CONSTRAINT `fk_history_pair` FOREIGN KEY (`pairActionId`) REFERENCES `history` (`id`),
    ADD CONSTRAINT `chk_history_ledger` CHECK (
        `ledgerVersion` IS NULL OR (
            `ledgerVersion` = 1
            AND `recordOrigin` IS NOT NULL
            AND `rentalKind` IS NOT NULL
            AND (
                (`action` IN ('RENT','FORCERENT') AND `pairActionId` IS NULL AND `closeReason` IS NULL)
                OR (`action` IN ('RETURN','FORCERETURN','REVERT')
                    AND `pairActionId` IS NOT NULL AND `closeReason` IS NOT NULL)
            )
        )
    );
