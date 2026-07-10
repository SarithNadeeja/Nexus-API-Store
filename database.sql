-- Nexus API Store - PostgreSQL schema
-- Import via install.php or psql

CREATE TABLE IF NOT EXISTS categories (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(500),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_listings (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(600),
    endpoint_url VARCHAR(500) NOT NULL,
    access_link VARCHAR(500) NOT NULL,
    api_key_value VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    price_coins INTEGER NOT NULL DEFAULT 50,
    category_id BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_listings_category FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE IF NOT EXISTS api_key_inventory (
    id BIGSERIAL PRIMARY KEY,
    api_listing_id BIGINT NOT NULL,
    key_link VARCHAR(500) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'AVAILABLE',
    assigned_user_id BIGINT NULL,
    api_purchase_id BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_at TIMESTAMP NULL,
    CONSTRAINT fk_api_key_inventory_listing FOREIGN KEY (api_listing_id) REFERENCES api_listings(id) ON DELETE CASCADE,
    CONSTRAINT fk_api_key_inventory_user FOREIGN KEY (assigned_user_id) REFERENCES app_users(id),
    CONSTRAINT uk_api_key_inventory_link UNIQUE (key_link)
);

CREATE INDEX IF NOT EXISTS idx_api_key_inventory_listing_status ON api_key_inventory (api_listing_id, status);

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    must_change_credentials BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS coin_packages (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    coin_amount INTEGER NOT NULL,
    price_usd NUMERIC(10,2) NOT NULL,
    tone VARCHAR(40) NOT NULL DEFAULT 'package-blue',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coin_settings (
    id INTEGER PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    custom_recharge_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    custom_coin_price_usd NUMERIC(10,4) NOT NULL DEFAULT 3.00,
    custom_coin_min INTEGER NOT NULL DEFAULT 50,
    custom_coin_max INTEGER NOT NULL DEFAULT 100000,
    whatsapp_number VARCHAR(20) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO coin_settings (id, custom_recharge_enabled, custom_coin_price_usd, custom_coin_min, custom_coin_max, whatsapp_number)
SELECT 1, TRUE, 3.00, 50, 100000, ''
WHERE NOT EXISTS (SELECT 1 FROM coin_settings WHERE id = 1);

CREATE TABLE IF NOT EXISTS app_users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(160) NOT NULL UNIQUE,
    full_name VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    coin_balance INTEGER NOT NULL DEFAULT 0,
    email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coin_transactions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    transaction_type VARCHAR(40) NOT NULL,
    coin_amount INTEGER NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_coin_transactions_user FOREIGN KEY (user_id) REFERENCES app_users(id)
);

CREATE TABLE IF NOT EXISTS api_purchases (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    api_listing_id BIGINT NOT NULL,
    coins_spent INTEGER NOT NULL,
    purchased_key_snapshot VARCHAR(500) NOT NULL,
    access_link_snapshot VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_purchases_user FOREIGN KEY (user_id) REFERENCES app_users(id),
    CONSTRAINT fk_api_purchases_api FOREIGN KEY (api_listing_id) REFERENCES api_listings(id),
    CONSTRAINT uk_api_purchases_user_api UNIQUE (user_id, api_listing_id)
);

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_verification_tokens_user FOREIGN KEY (user_id) REFERENCES app_users(id)
);

-- Default admin: admin / Admin@123 (created by install.php if missing)
