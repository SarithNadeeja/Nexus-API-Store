#!/bin/bash
# Run on the server via SSH:
#   bash repair-db.sh
#
# Grants the nexus app user full access to all tables.

DB_NAME="nexus"
DB_USER="nexus"

sudo -u postgres psql -d "$DB_NAME" <<EOF
GRANT USAGE, CREATE ON SCHEMA public TO $DB_USER;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $DB_USER;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO $DB_USER;
ALTER TABLE IF EXISTS categories OWNER TO $DB_USER;
ALTER TABLE IF EXISTS api_listings OWNER TO $DB_USER;
ALTER TABLE IF EXISTS admin_users OWNER TO $DB_USER;
ALTER TABLE IF EXISTS app_users OWNER TO $DB_USER;
ALTER TABLE IF EXISTS coin_transactions OWNER TO $DB_USER;
ALTER TABLE IF EXISTS api_purchases OWNER TO $DB_USER;
ALTER TABLE IF EXISTS email_verification_tokens OWNER TO $DB_USER;
ALTER SEQUENCE IF EXISTS categories_id_seq OWNER TO $DB_USER;
ALTER SEQUENCE IF EXISTS api_listings_id_seq OWNER TO $DB_USER;
ALTER SEQUENCE IF EXISTS admin_users_id_seq OWNER TO $DB_USER;
ALTER SEQUENCE IF EXISTS app_users_id_seq OWNER TO $DB_USER;
ALTER SEQUENCE IF EXISTS coin_transactions_id_seq OWNER TO $DB_USER;
ALTER SEQUENCE IF EXISTS api_purchases_id_seq OWNER TO $DB_USER;
ALTER SEQUENCE IF EXISTS email_verification_tokens_id_seq OWNER TO $DB_USER;
EOF

echo "Done. Test: curl https://nexusapistore.com/api/public/apis"
