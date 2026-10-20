-- MySQL 8.0 schema for Stone (Pagar.me) integration hub
-- Engine: InnoDB, Charset: utf8mb4

-- Optional: create database
-- CREATE DATABASE IF NOT EXISTS stone CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE stone;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Sites cadastrados (tenancy)
CREATE TABLE IF NOT EXISTS sites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  domain VARCHAR(255) NULL,
  callback_url TEXT NOT NULL,
  stone_account_id VARCHAR(64) NULL,
  pagarme_account_id BIGINT UNSIGNED NULL,
  client_secret_hint VARCHAR(16) NULL,
  webhook_forward_secret VARCHAR(255) NULL,
  api_token VARCHAR(128) NOT NULL UNIQUE,
  client_id VARCHAR(64) NOT NULL UNIQUE,
  client_secret_hash VARCHAR(255) NOT NULL,
  -- Who pays interest for installments in quotes and pricing rules
  interest_payer ENUM('user','merchant') NOT NULL DEFAULT 'user',
  installments_free INT UNSIGNED NOT NULL DEFAULT 1,
  -- Require billing_address for card payments (NULL = use env default)
  require_billing_address TINYINT(1) NULL DEFAULT NULL,
  status ENUM('active','suspended','disabled') NOT NULL DEFAULT 'active',
  rate_limit_per_minute INT NOT NULL DEFAULT 60,
  breaker_open_until TIMESTAMP NULL DEFAULT NULL,
  breaker_failure_count INT NOT NULL DEFAULT 0,
  breaker_window_start TIMESTAMP NULL DEFAULT NULL,
  ip_whitelist TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contas do Pagar.me (multi-account)
CREATE TABLE IF NOT EXISTS pagarme_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  stone_account_uid VARCHAR(64) NULL,
  active_env ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  api_key_sandbox VARCHAR(255) NULL,
  api_key_production VARCHAR(255) NULL,
  public_key_sandbox VARCHAR(255) NULL,
  public_key_production VARCHAR(255) NULL,
  webhook_secret_sandbox VARCHAR(255) NULL,
  webhook_secret_production VARCHAR(255) NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate limit counters por site (janela de 1 minuto)
CREATE TABLE IF NOT EXISTS site_rate_counters (
  site_id BIGINT UNSIGNED NOT NULL,
  window_start_minute DATETIME NOT NULL,
  `count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (site_id, window_start_minute),
  CONSTRAINT fk_src_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clientes finais (opcional, conforme uso)
CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id BIGINT UNSIGNED NOT NULL,
  stone_customer_id VARCHAR(128) NULL,
  env ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  name VARCHAR(150) NULL,
  email VARCHAR(150) NULL,
  document VARCHAR(32) NULL,
  phone VARCHAR(32) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_site_stone_customer (site_id, stone_customer_id),
  KEY idx_customers_site (site_id),
  CONSTRAINT fk_customers_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Histórico de alterações de nome do cliente (por tentativa de pagamento)
CREATE TABLE IF NOT EXISTS customer_name_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NULL,
  old_name VARCHAR(150) NULL,
  new_name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cnh_customer (customer_id),
  KEY idx_cnh_payment (payment_id),
  CONSTRAINT fk_cnh_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_cnh_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Métodos de pagamento (tokens/cartões/pix)
CREATE TABLE IF NOT EXISTS payment_methods (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  type ENUM('card','pix','boleto') NOT NULL,
  stone_token VARCHAR(128) NULL,
  brand VARCHAR(32) NULL,
  last4 VARCHAR(4) NULL,
  exp_month TINYINT UNSIGNED NULL,
  exp_year SMALLINT UNSIGNED NULL,
  holder_name VARCHAR(150) NULL,
  fingerprint_hash VARCHAR(128) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_stone_token (stone_token),
  KEY idx_payment_methods_site (site_id),
  CONSTRAINT fk_payment_methods_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_methods_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pagamentos
CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  payment_method_id BIGINT UNSIGNED NULL,
  provider_event_id VARCHAR(128) NULL,
  stone_payment_id VARCHAR(128) NULL,
  env ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  client_reference_id VARCHAR(64) NOT NULL, -- referência de negócio (pode repetir para histórico/parcelas)
  idempotency_key VARCHAR(96) NULL, -- chave de idempotência técnica (opcional por requisição)
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  payment_method_type ENUM('credit_card','debit_card','pix','boleto') NULL,
  description TEXT NULL,
  customer_name VARCHAR(150) NULL,
  is_invoice TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('created','queued','processing','authorized','paid','failed','canceled','refunded','chargeback','deleted') NOT NULL DEFAULT 'created',
  cancel_reason TEXT NULL,
  request_payload LONGTEXT NULL,
  response_payload LONGTEXT NULL,
  metadata LONGTEXT NULL,
  last_error TEXT NULL,
  notified_site TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_site_idempotency (site_id, idempotency_key),
  KEY idx_payments_site_client_ref (site_id, client_reference_id),
  UNIQUE KEY uniq_stone_payment (stone_payment_id),
  KEY idx_payments_provider_event (provider_event_id),
  KEY idx_payments_site (site_id),
  KEY idx_payments_status (status),
  KEY idx_payments_created_at (created_at),
  KEY idx_payments_is_invoice (is_invoice),
  CONSTRAINT fk_payments_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Histórico de status de pagamento
CREATE TABLE IF NOT EXISTS payment_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(32) NULL,
  new_status VARCHAR(32) NOT NULL,
  reason TEXT NULL,
  context LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_history_payment (payment_id),
  KEY idx_status_history_created (created_at),
  CONSTRAINT fk_status_history_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inbox de webhooks recebidos da Stone
CREATE TABLE IF NOT EXISTS inbound_webhooks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider VARCHAR(32) NOT NULL DEFAULT 'stone',
  provider_event_id VARCHAR(128) NOT NULL,
  event_type VARCHAR(64) NULL,
  stone_payment_id VARCHAR(128) NULL,
  stone_subscription_id VARCHAR(128) NULL,
  site_id BIGINT UNSIGNED NULL,
  payment_id BIGINT UNSIGNED NULL,
  raw_payload LONGTEXT NOT NULL,
  headers LONGTEXT NULL,
  signature VARCHAR(255) NULL,
  request_id VARCHAR(64) NULL,
  status ENUM('pending','processing','processed','discarded','failed') NOT NULL DEFAULT 'pending',
  error TEXT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_inbound_event (provider, provider_event_id),
  KEY idx_inbound_status (status),
  KEY idx_inbound_payment (stone_payment_id),
  KEY idx_inbound_site (site_id),
  CONSTRAINT fk_inbound_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
  CONSTRAINT fk_inbound_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Logs de forward ao site (outbox + histórico de tentativas)
CREATE TABLE IF NOT EXISTS webhook_forward_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NULL,
  site_id BIGINT UNSIGNED NOT NULL,
  target_url TEXT NOT NULL,
  attempt INT NOT NULL DEFAULT 1,
  request_body LONGTEXT NULL,
  response_status INT NULL,
  response_body TEXT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  error TEXT NULL,
  request_id VARCHAR(64) NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_forward_site (site_id),
  KEY idx_forward_notification_attempt (notification_id, attempt),
  CONSTRAINT fk_forward_notification FOREIGN KEY (notification_id) REFERENCES inbound_webhooks(id) ON DELETE CASCADE,
  CONSTRAINT fk_forward_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
  CONSTRAINT fk_forward_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configurações da conta (globais)
CREATE TABLE IF NOT EXISTS account_settings (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  stone_webhook_basic_user VARCHAR(150) NULL,
  stone_webhook_basic_pass_hash VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assinaturas (recorrência)
CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  stone_subscription_id VARCHAR(128) NULL,
  env ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  plan_id VARCHAR(64) NULL,
  amount_cents INT UNSIGNED NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  status ENUM('active','trialing','past_due','paused','canceled','expired','deleted') NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  metadata LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_stone_subscription (stone_subscription_id),
  KEY idx_subscriptions_site (site_id),
  KEY idx_subscriptions_customer (customer_id),
  CONSTRAINT fk_subscriptions_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscriptions_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscription_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subscription_id BIGINT UNSIGNED NOT NULL,
  provider_event_id VARCHAR(128) NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  occurrence INT UNSIGNED NULL,
  card_installments INT UNSIGNED NULL,
  raw_payload LONGTEXT NULL,
  occurred_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_subscription_event (subscription_id, provider_event_id),
  CONSTRAINT fk_subscription_events_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Idempotência por escopo (futuro uso)
CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id BIGINT UNSIGNED NOT NULL,
  scope VARCHAR(64) NOT NULL,
  `key` VARCHAR(128) NOT NULL,
  request_hash CHAR(64) NULL,
  response_status INT NULL,
  response_body LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_site_scope_key (site_id, scope, `key`),
  CONSTRAINT fk_idem_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auditoria
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id BIGINT UNSIGNED NULL,
  actor_type ENUM('system','site_api','admin') NOT NULL DEFAULT 'system',
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(128) NOT NULL,
  entity_type VARCHAR(64) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_site (site_id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_action (action),
  CONSTRAINT fk_audit_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin users and login logs
-- Links de pagamento (checkout)
CREATE TABLE IF NOT EXISTS payment_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id BIGINT UNSIGNED NOT NULL,
  stone_order_id VARCHAR(128) NULL,
  code VARCHAR(64) NULL,
  env ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  status VARCHAR(32) NULL,
  checkout_url TEXT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  expires_in_minutes INT NULL,
  customer_name VARCHAR(150) NULL,
  customer_email VARCHAR(150) NULL,
  request_payload LONGTEXT NULL,
  response_payload LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_site_code (site_id, code),
  UNIQUE KEY uniq_stone_order (stone_order_id),
  KEY idx_payment_links_site (site_id),
  KEY idx_payment_links_created (created_at),
  CONSTRAINT fk_payment_links_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Admin users and login logs
CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','operator','viewer') NOT NULL DEFAULT 'admin',
  twofa_secret VARCHAR(64) NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_login_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id BIGINT UNSIGNED NULL,
  email VARCHAR(190) NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  request_id VARCHAR(64) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_login_user (admin_user_id),
  KEY idx_admin_login_request (request_id),
  CONSTRAINT fk_admin_login_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin access/navigation logs
CREATE TABLE IF NOT EXISTS admin_access_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id BIGINT UNSIGNED NULL,
  method VARCHAR(8) NULL,
  path TEXT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  request_id VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_access_user (admin_user_id),
  KEY idx_admin_access_request (request_id),
  KEY idx_admin_access_created (created_at),
  CONSTRAINT fk_admin_access_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Soft deletions audit (logical delete registry)
CREATE TABLE IF NOT EXISTS soft_deletions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  table_name VARCHAR(64) NOT NULL,
  record_id BIGINT UNSIGNED NOT NULL,
  record_type VARCHAR(64) NULL,
  reason TEXT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  deleted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_softdel_table_record (table_name, record_id),
  KEY idx_softdel_admin (admin_user_id),
  CONSTRAINT fk_softdel_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Plans (subscription plans per site)
CREATE TABLE IF NOT EXISTS plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(64) NOT NULL,
  env ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  description TEXT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  interval_unit ENUM('day','week','month','year') NOT NULL DEFAULT 'month',
  interval_count INT UNSIGNED NOT NULL DEFAULT 1,
  billing_cycles INT UNSIGNED NOT NULL DEFAULT 1,
  stone_plan_id VARCHAR(128) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_site_code (site_id, code),
  UNIQUE KEY uniq_site_stone_plan (site_id, stone_plan_id),
  KEY idx_plans_site (site_id),
  CONSTRAINT fk_plans_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_request_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NULL,
    method VARCHAR(10) NOT NULL,
    path VARCHAR(255) NOT NULL,
    status INT NOT NULL,
    duration_ms INT NOT NULL,
    ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    auth_type VARCHAR(32) NULL,
    client_id VARCHAR(80) NULL,
    request_body MEDIUMTEXT NULL,
    response_body MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_site_created (site_id, created_at),
    INDEX idx_path_created (path, created_at),
    INDEX idx_status_created (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
