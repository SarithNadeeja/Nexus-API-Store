-- Run this on database "nexus" as a PostgreSQL superuser (e.g. postgres)
-- psql -U postgres -d nexus -f database-grants.sql

GRANT USAGE ON SCHEMA public TO nexus;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO nexus;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO nexus;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO nexus;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO nexus;

ALTER TABLE IF EXISTS categories OWNER TO nexus;
ALTER TABLE IF EXISTS api_listings OWNER TO nexus;
ALTER TABLE IF EXISTS admin_users OWNER TO nexus;
ALTER TABLE IF EXISTS app_users OWNER TO nexus;
ALTER TABLE IF EXISTS coin_transactions OWNER TO nexus;
ALTER TABLE IF EXISTS api_purchases OWNER TO nexus;
ALTER TABLE IF EXISTS coin_packages OWNER TO nexus;
ALTER TABLE IF EXISTS coin_settings OWNER TO nexus;
ALTER TABLE IF EXISTS email_verification_tokens OWNER TO nexus;

ALTER SEQUENCE IF EXISTS categories_id_seq OWNER TO nexus;
ALTER SEQUENCE IF EXISTS api_listings_id_seq OWNER TO nexus;
ALTER SEQUENCE IF EXISTS admin_users_id_seq OWNER TO nexus;
ALTER SEQUENCE IF EXISTS app_users_id_seq OWNER TO nexus;
ALTER SEQUENCE IF EXISTS coin_transactions_id_seq OWNER TO nexus;
ALTER SEQUENCE IF EXISTS api_purchases_id_seq OWNER TO nexus;
ALTER SEQUENCE IF EXISTS coin_packages_id_seq OWNER TO nexus;
ALTER SEQUENCE IF EXISTS email_verification_tokens_id_seq OWNER TO nexus;
