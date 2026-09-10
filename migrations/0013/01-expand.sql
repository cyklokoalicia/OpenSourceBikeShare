-- Prepare lookups without adding columns or changing existing pairs.
ALTER TABLE `history`
    ADD KEY `idx_history_rental_state` (`bikeNum`, `action`, `id`);
