-- Create buy_all_summary and buy_all_items tables if they don't exist.
-- These are defined in schema.sql but were never in a migration,
-- so incrementally-migrated servers may not have them.

CREATE TABLE IF NOT EXISTS buy_all_summary (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mode_key VARCHAR(40) NOT NULL,
    sort_key VARCHAR(40) NOT NULL,
    filters_hash CHAR(64) NOT NULL,
    summary_json LONGTEXT NOT NULL,
    payload_json LONGTEXT NOT NULL,
    computed_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_buy_all_summary_request (mode_key, sort_key, filters_hash),
    KEY idx_buy_all_summary_lookup (mode_key, sort_key, filters_hash, computed_at),
    KEY idx_buy_all_summary_computed (computed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS buy_all_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    summary_id BIGINT UNSIGNED NOT NULL,
    page_number INT UNSIGNED NOT NULL DEFAULT 1,
    rank_position INT UNSIGNED NOT NULL DEFAULT 0,
    type_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    mode_rank_score DECIMAL(8,2) DEFAULT NULL,
    necessity_score DECIMAL(8,2) DEFAULT NULL,
    profit_score DECIMAL(8,2) DEFAULT NULL,
    item_json LONGTEXT NOT NULL,
    computed_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_buy_all_items_summary_type_page (summary_id, type_id, page_number),
    KEY idx_buy_all_items_summary_page_rank (summary_id, page_number, rank_position),
    KEY idx_buy_all_items_item_id (type_id),
    KEY idx_buy_all_items_type_computed (type_id, computed_at),
    KEY idx_buy_all_items_computed (computed_at),
    CONSTRAINT fk_buy_all_items_summary FOREIGN KEY (summary_id) REFERENCES buy_all_summary(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
