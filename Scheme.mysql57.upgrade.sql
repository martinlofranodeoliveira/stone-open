-- MySQL 5.7+/8.0 upgrade script to use native JSON columns
-- Use this AFTER running the base Scheme.sql (which is MySQL 5.6 compatible with LONGTEXT).
-- This script converts LONGTEXT columns that store JSON into native JSON type.

SET FOREIGN_KEY_CHECKS = 0;

-- payments
ALTER TABLE payments
  MODIFY request_payload JSON NULL,
  MODIFY response_payload JSON NULL,
  MODIFY metadata JSON NULL;

-- Allow duplicated business reference (client_reference_id) while preserving
-- technical idempotency by request key.
SET @db := DATABASE();

SET @has_idem_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'payments'
    AND COLUMN_NAME = 'idempotency_key'
);
SET @sql := IF(
  @has_idem_col = 0,
  'ALTER TABLE payments ADD COLUMN idempotency_key VARCHAR(96) NULL AFTER client_reference_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_ref_uq := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'payments'
    AND INDEX_NAME = 'uniq_site_client_ref'
);
SET @sql := IF(
  @has_old_ref_uq > 0,
  'ALTER TABLE payments DROP INDEX uniq_site_client_ref',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idem_uq := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'payments'
    AND INDEX_NAME = 'uniq_site_idempotency'
);
SET @sql := IF(
  @has_idem_uq = 0,
  'CREATE UNIQUE INDEX uniq_site_idempotency ON payments (site_id, idempotency_key)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_ref_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'payments'
    AND INDEX_NAME = 'idx_payments_site_client_ref'
);
SET @sql := IF(
  @has_ref_idx = 0,
  'CREATE INDEX idx_payments_site_client_ref ON payments (site_id, client_reference_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payments.cancel_reason
SET @has_cancel_reason := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'payments'
    AND COLUMN_NAME = 'cancel_reason'
);
SET @sql := IF(
  @has_cancel_reason = 0,
  'ALTER TABLE payments ADD COLUMN cancel_reason TEXT NULL AFTER status',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- customers.env
SET @has_customers_env := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'customers'
    AND COLUMN_NAME = 'env'
);
SET @sql := IF(
  @has_customers_env = 0,
  'ALTER TABLE customers ADD COLUMN env ENUM(\"sandbox\",\"production\") NOT NULL DEFAULT \"sandbox\" AFTER stone_customer_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payments.env
SET @has_payments_env := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'payments'
    AND COLUMN_NAME = 'env'
);
SET @sql := IF(
  @has_payments_env = 0,
  'ALTER TABLE payments ADD COLUMN env ENUM(\"sandbox\",\"production\") NOT NULL DEFAULT \"sandbox\" AFTER stone_payment_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- plans.env
SET @has_plans_env := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'plans'
    AND COLUMN_NAME = 'env'
);
SET @sql := IF(
  @has_plans_env = 0,
  'ALTER TABLE plans ADD COLUMN env ENUM(\"sandbox\",\"production\") NOT NULL DEFAULT \"sandbox\" AFTER code',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- subscriptions.env
SET @has_subs_env := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'subscriptions'
    AND COLUMN_NAME = 'env'
);
SET @sql := IF(
  @has_subs_env = 0,
  'ALTER TABLE subscriptions ADD COLUMN env ENUM(\"sandbox\",\"production\") NOT NULL DEFAULT \"sandbox\" AFTER stone_subscription_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payment_links.env
SET @has_plinks_env := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'payment_links'
    AND COLUMN_NAME = 'env'
);
SET @sql := IF(
  @has_plinks_env = 0,
  'ALTER TABLE payment_links ADD COLUMN env ENUM(\"sandbox\",\"production\") NOT NULL DEFAULT \"sandbox\" AFTER code',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payment_status_history
ALTER TABLE payment_status_history
  MODIFY context JSON NULL;

-- inbound_webhooks
ALTER TABLE inbound_webhooks
  MODIFY raw_payload JSON NOT NULL,
  MODIFY headers JSON NULL;

-- webhook_forward_logs
ALTER TABLE webhook_forward_logs
  MODIFY request_body JSON NULL,
  MODIFY response_body JSON NULL;

-- subscriptions
ALTER TABLE subscriptions
  MODIFY metadata JSON NULL;

-- subscription_events
ALTER TABLE subscription_events
  MODIFY raw_payload JSON NULL;

-- audit_logs
ALTER TABLE audit_logs
  MODIFY details JSON NULL;

-- payment_links
ALTER TABLE payment_links
  MODIFY request_payload JSON NULL,
  MODIFY response_payload JSON NULL;

-- customer_name_history
SET @has_cnh_table := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'customer_name_history'
);
SET @sql := IF(
  @has_cnh_table = 0,
  'CREATE TABLE customer_name_history (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, customer_id BIGINT UNSIGNED NOT NULL, payment_id BIGINT UNSIGNED NULL, old_name VARCHAR(150) NULL, new_name VARCHAR(150) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_cnh_customer (customer_id), KEY idx_cnh_payment (payment_id), CONSTRAINT fk_cnh_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE, CONSTRAINT fk_cnh_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- Optional performance indexes for MySQL 5.7+/8.0
-- These help common queries (queue scans, admin lists, and timeline views)

-- Faster selection of next pending webhook
CREATE INDEX IF NOT EXISTS idx_inbound_status_id ON inbound_webhooks (status, id);

-- Common admin filters: site + status + created_at
CREATE INDEX IF NOT EXISTS idx_pay_site_status_created ON payments (site_id, status, created_at);

-- Forward logs: by site/time and by success/time
CREATE INDEX IF NOT EXISTS idx_forward_site_sent ON webhook_forward_logs (site_id, sent_at);
CREATE INDEX IF NOT EXISTS idx_forward_success_sent ON webhook_forward_logs (success, sent_at);

-- Subscription events: timeline by subscription
CREATE INDEX IF NOT EXISTS idx_sub_events_sub_occurred ON subscription_events (subscription_id, occurred_at);
