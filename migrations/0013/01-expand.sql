-- Additive phase: leaves existing pairs and unverified rows unchanged.
ALTER TABLE `history`
    ADD COLUMN `ledgerVersion` TINYINT UNSIGNED DEFAULT NULL,
    ADD COLUMN `recordOrigin` ENUM('command','synthetic','reconstructed','legacy_unknown') DEFAULT NULL,
    ADD COLUMN `rentalKind` ENUM('rental','correction','service') DEFAULT NULL,
    ADD COLUMN `closeReason` ENUM('returned','forced_return','handover','relocation','cancelled') DEFAULT NULL,
    ADD KEY `idx_history_rental_state` (`bikeNum`, `ledgerVersion`, `action`, `id`);
