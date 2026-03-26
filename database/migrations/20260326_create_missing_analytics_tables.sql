-- Create analytics and doctrine time-series tables that sync jobs depend on.
-- These tables are defined in schema.sql but may be missing from servers
-- that were set up before they were added.

CREATE TABLE IF NOT EXISTS market_item_stock_1h (
    bucket_start DATETIME NOT NULL,
    source_type ENUM('alliance_structure', 'market_hub') NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    type_id INT UNSIGNED NOT NULL,
    sample_count INT UNSIGNED NOT NULL DEFAULT 0,
    stock_units_sum DECIMAL(20, 2) NOT NULL DEFAULT 0.00,
    listing_count_sum DECIMAL(20, 2) NOT NULL DEFAULT 0.00,
    local_stock_units BIGINT NOT NULL DEFAULT 0,
    listing_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bucket_start, source_type, source_id, type_id),
    KEY idx_market_item_stock_1h_bucket_type (bucket_start, type_id),
    KEY idx_market_item_stock_1h_type_bucket (type_id, bucket_start),
    KEY idx_market_item_stock_1h_bucket (bucket_start),
    KEY idx_market_item_stock_1h_source_bucket (source_type, source_id, bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS market_item_stock_1d (
    bucket_start DATE NOT NULL,
    source_type ENUM('alliance_structure', 'market_hub') NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    type_id INT UNSIGNED NOT NULL,
    sample_count INT UNSIGNED NOT NULL DEFAULT 0,
    stock_units_sum DECIMAL(20, 2) NOT NULL DEFAULT 0.00,
    listing_count_sum DECIMAL(20, 2) NOT NULL DEFAULT 0.00,
    local_stock_units BIGINT NOT NULL DEFAULT 0,
    listing_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bucket_start, source_type, source_id, type_id),
    KEY idx_market_item_stock_1d_bucket_type (bucket_start, type_id),
    KEY idx_market_item_stock_1d_type_bucket (type_id, bucket_start),
    KEY idx_market_item_stock_1d_bucket (bucket_start),
    KEY idx_market_item_stock_1d_source_bucket (source_type, source_id, bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS market_item_price_1h (
    bucket_start DATETIME NOT NULL,
    source_type ENUM('alliance_structure', 'market_hub') NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    type_id INT UNSIGNED NOT NULL,
    sample_count INT UNSIGNED NOT NULL DEFAULT 0,
    listing_count_sum DECIMAL(20, 2) NOT NULL DEFAULT 0.00,
    avg_price_sum DECIMAL(20, 2) NOT NULL DEFAULT 0.00,
    weighted_price_numerator DECIMAL(24, 2) NOT NULL DEFAULT 0.00,
    weighted_price_denominator DECIMAL(24, 2) NOT NULL DEFAULT 0.00,
    listing_count INT UNSIGNED NOT NULL DEFAULT 0,
    min_price DECIMAL(20, 2) DEFAULT NULL,
    max_price DECIMAL(20, 2) DEFAULT NULL,
    avg_price DECIMAL(20, 2) DEFAULT NULL,
    weighted_price DECIMAL(20, 2) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bucket_start, source_type, source_id, type_id),
    KEY idx_market_item_price_1h_bucket_type (bucket_start, type_id),
    KEY idx_market_item_price_1h_type_bucket (type_id, bucket_start),
    KEY idx_market_item_price_1h_bucket (bucket_start),
    KEY idx_market_item_price_1h_source_bucket (source_type, source_id, bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS market_item_price_1d (
    bucket_start DATE NOT NULL,
    source_type ENUM('alliance_structure', 'market_hub') NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    type_id INT UNSIGNED NOT NULL,
    sample_count INT UNSIGNED NOT NULL DEFAULT 0,
    listing_count_sum DECIMAL(20, 2) NOT NULL DEFAULT 0.00,
    avg_price_sum DECIMAL(20, 2) NOT NULL DEFAULT 0.00,
    weighted_price_numerator DECIMAL(24, 2) NOT NULL DEFAULT 0.00,
    weighted_price_denominator DECIMAL(24, 2) NOT NULL DEFAULT 0.00,
    listing_count INT UNSIGNED NOT NULL DEFAULT 0,
    min_price DECIMAL(20, 2) DEFAULT NULL,
    max_price DECIMAL(20, 2) DEFAULT NULL,
    avg_price DECIMAL(20, 2) DEFAULT NULL,
    weighted_price DECIMAL(20, 2) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bucket_start, source_type, source_id, type_id),
    KEY idx_market_item_price_1d_bucket_type (bucket_start, type_id),
    KEY idx_market_item_price_1d_type_bucket (type_id, bucket_start),
    KEY idx_market_item_price_1d_bucket (bucket_start),
    KEY idx_market_item_price_1d_source_bucket (source_type, source_id, bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS doctrine_item_stock_1d (
    bucket_start DATE NOT NULL,
    fit_id INT UNSIGNED NOT NULL,
    doctrine_group_id INT UNSIGNED DEFAULT NULL,
    type_id INT UNSIGNED NOT NULL,
    required_units INT UNSIGNED NOT NULL DEFAULT 0,
    local_stock_units BIGINT NOT NULL DEFAULT 0,
    complete_fits_supported INT UNSIGNED NOT NULL DEFAULT 0,
    fit_gap INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bucket_start, fit_id, type_id),
    KEY idx_doctrine_item_stock_1d_group_bucket (doctrine_group_id, bucket_start),
    KEY idx_doctrine_item_stock_1d_type_bucket (type_id, bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS doctrine_fit_activity_1d (
    bucket_start DATE NOT NULL,
    fit_id INT UNSIGNED NOT NULL,
    hull_type_id INT UNSIGNED DEFAULT NULL,
    doctrine_group_id INT UNSIGNED DEFAULT NULL,
    hull_loss_count INT UNSIGNED NOT NULL DEFAULT 0,
    doctrine_item_loss_count INT UNSIGNED NOT NULL DEFAULT 0,
    complete_fits_available INT UNSIGNED NOT NULL DEFAULT 0,
    target_fits INT UNSIGNED NOT NULL DEFAULT 0,
    fit_gap INT UNSIGNED NOT NULL DEFAULT 0,
    readiness_state VARCHAR(32) NOT NULL DEFAULT 'unknown',
    resupply_pressure VARCHAR(64) NOT NULL DEFAULT 'stable',
    priority_score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bucket_start, fit_id),
    KEY idx_doctrine_fit_activity_1d_bucket_fit (bucket_start, fit_id),
    KEY idx_doctrine_fit_activity_1d_bucket_group (bucket_start, doctrine_group_id),
    KEY idx_doctrine_fit_activity_1d_bucket (bucket_start),
    KEY idx_doctrine_fit_activity_1d_group_bucket (doctrine_group_id, bucket_start),
    KEY idx_doctrine_fit_activity_1d_hull_bucket (hull_type_id, bucket_start),
    KEY idx_doctrine_fit_activity_1d_priority (priority_score, bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS killmail_item_loss_1d (
    bucket_start DATE NOT NULL,
    type_id INT UNSIGNED NOT NULL,
    doctrine_fit_id INT UNSIGNED DEFAULT NULL,
    doctrine_group_id INT UNSIGNED DEFAULT NULL,
    hull_type_id INT UNSIGNED DEFAULT NULL,
    doctrine_fit_key INT UNSIGNED GENERATED ALWAYS AS (COALESCE(doctrine_fit_id, 0)) STORED,
    doctrine_group_key INT UNSIGNED GENERATED ALWAYS AS (COALESCE(doctrine_group_id, 0)) STORED,
    hull_type_key INT UNSIGNED GENERATED ALWAYS AS (COALESCE(hull_type_id, 0)) STORED,
    loss_count INT UNSIGNED NOT NULL DEFAULT 0,
    quantity_lost BIGINT UNSIGNED NOT NULL DEFAULT 0,
    victim_count INT UNSIGNED NOT NULL DEFAULT 0,
    killmail_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_killmail_item_loss_1d_dimensions (bucket_start, type_id, doctrine_fit_key, doctrine_group_key, hull_type_key),
    KEY idx_killmail_item_loss_1d_bucket_type (bucket_start, type_id),
    KEY idx_killmail_item_loss_1d_type_bucket (type_id, bucket_start),
    KEY idx_killmail_item_loss_1d_bucket (bucket_start),
    KEY idx_killmail_item_loss_1d_group_bucket (doctrine_group_id, bucket_start),
    KEY idx_killmail_item_loss_1d_fit_bucket (doctrine_fit_id, bucket_start),
    KEY idx_killmail_item_loss_1d_hull_bucket (hull_type_id, bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
