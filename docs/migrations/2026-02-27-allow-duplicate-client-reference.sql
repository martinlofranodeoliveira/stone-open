-- Permite múltiplos pagamentos com a mesma client_reference_id (histórico/parcelamento)
-- e mantém idempotência técnica por idempotency_key.
--
-- Uso:
--   mysql -u USER -p DATABASE < docs/migrations/2026-02-27-allow-duplicate-client-reference.sql

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
