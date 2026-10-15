CREATE TABLE IF NOT EXISTS computers (
  computer_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  lab_id VARCHAR(20) NOT NULL,
  mac_address VARCHAR(17) NOT NULL UNIQUE,
  hostname VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45) NULL,
  status ENUM('online', 'offline', 'idle', 'exam_lock') NOT NULL DEFAULT 'offline',
  cpu_usage DECIMAL(5,2) NOT NULL DEFAULT 0,
  ram_usage DECIMAL(5,2) NOT NULL DEFAULT 0,
  disk_usage DECIMAL(5,2) NOT NULL DEFAULT 0,
  active_user VARCHAR(100) NULL,
  missing_peripherals JSON NULL,
  last_heartbeat_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_computers_lab FOREIGN KEY (lab_id) REFERENCES labs (lab_id),
  INDEX idx_computers_lab_status (lab_id, status),
  INDEX idx_computers_last_heartbeat (last_heartbeat_at)
);

ALTER TABLE computers
  ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL,
  ADD COLUMN IF NOT EXISTS status ENUM('online', 'offline', 'idle', 'exam_lock') NOT NULL DEFAULT 'offline',
  ADD COLUMN IF NOT EXISTS cpu_usage DECIMAL(5,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS ram_usage DECIMAL(5,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS disk_usage DECIMAL(5,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS active_user VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS missing_peripherals JSON NULL,
  ADD COLUMN IF NOT EXISTS last_heartbeat_at TIMESTAMP NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS telemetry_logs (
  telemetry_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  computer_id BIGINT UNSIGNED NULL,
  mac_address VARCHAR(17) NOT NULL,
  hostname VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45) NULL,
  cpu_usage DECIMAL(5,2) NOT NULL,
  ram_usage DECIMAL(5,2) NOT NULL,
  disk_usage DECIMAL(5,2) NOT NULL,
  network_latency_ms INT NULL,
  active_user VARCHAR(100) NULL,
  peripherals JSON NULL,
  received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_telemetry_logs_mac_received (mac_address, received_at),
  CONSTRAINT fk_telemetry_logs_computer FOREIGN KEY (computer_id) REFERENCES computers (computer_id)
);
