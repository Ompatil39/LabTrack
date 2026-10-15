CREATE TABLE IF NOT EXISTS labs (
  lab_id VARCHAR(20) PRIMARY KEY,
  lab_name VARCHAR(100) NOT NULL,
  status ENUM('Active', 'InActive', 'In Repair') NOT NULL,
  department VARCHAR(100) NOT NULL,
  lab_incharge VARCHAR(100) NOT NULL,
  establishment_date DATE NOT NULL,
  room_capacity INT NOT NULL,
  building VARCHAR(100),
  room_number VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  user_id VARCHAR(20) PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  user_type ENUM('Admin', 'Incharge') NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  contact_number VARCHAR(20),
  org_name VARCHAR(160),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS devices (
  device_id VARCHAR(20) PRIMARY KEY,
  lab_id VARCHAR(20) NOT NULL,
  device_type ENUM('PC', 'Printer', 'Mouse', 'Keyboard', 'Monitor', 'CPU') NOT NULL,
  device_name VARCHAR(100) NOT NULL,
  serial_number VARCHAR(100),
  status ENUM('Active', 'InActive', 'Under Repair') NOT NULL,
  qr_code TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_devices_lab FOREIGN KEY (lab_id) REFERENCES labs (lab_id)
);

CREATE TABLE IF NOT EXISTS pc_details (
  pc_id INT PRIMARY KEY AUTO_INCREMENT,
  device_id VARCHAR(20) NOT NULL,
  processor VARCHAR(100) NOT NULL,
  ram VARCHAR(50) NOT NULL,
  storage VARCHAR(100) NOT NULL,
  operating_system VARCHAR(100) NOT NULL,
  ethernet_mac VARCHAR(100),
  wifi_adapter VARCHAR(100),
  ip_address VARCHAR(50),
  CONSTRAINT fk_pc_details_device FOREIGN KEY (device_id) REFERENCES devices (device_id)
);

CREATE TABLE IF NOT EXISTS monitors (
  monitor_id INT PRIMARY KEY AUTO_INCREMENT,
  device_id VARCHAR(20) NOT NULL,
  brand_model VARCHAR(100) NOT NULL,
  resolution VARCHAR(50) NOT NULL,
  serial_number VARCHAR(100),
  status ENUM('Active', 'In Repair', 'Faulty') NOT NULL,
  CONSTRAINT fk_monitors_device FOREIGN KEY (device_id) REFERENCES devices (device_id)
);

CREATE TABLE IF NOT EXISTS keyboards (
  keyboard_id INT PRIMARY KEY AUTO_INCREMENT,
  device_id VARCHAR(20) NOT NULL,
  keyboard_name VARCHAR(100),
  keyboard_type ENUM('Wired', 'Wireless', 'Mechanical', 'Membrane') NOT NULL,
  serial_number VARCHAR(100),
  status ENUM('Active', 'In Repair', 'Faulty') NOT NULL,
  CONSTRAINT fk_keyboards_device FOREIGN KEY (device_id) REFERENCES devices (device_id)
);

CREATE TABLE IF NOT EXISTS mice (
  mouse_id INT PRIMARY KEY AUTO_INCREMENT,
  device_id VARCHAR(20) NOT NULL,
  mouse_name VARCHAR(100),
  mouse_type ENUM('Wired', 'Wireless') NOT NULL,
  serial_number VARCHAR(100),
  status ENUM('Active', 'In Repair', 'Faulty') NOT NULL,
  CONSTRAINT fk_mice_device FOREIGN KEY (device_id) REFERENCES devices (device_id)
);

CREATE TABLE IF NOT EXISTS cpus (
  cpu_id INT PRIMARY KEY AUTO_INCREMENT,
  device_id VARCHAR(20) NOT NULL,
  case_model VARCHAR(100),
  serial_number VARCHAR(100),
  power_supply VARCHAR(50) NOT NULL,
  status ENUM('Active', 'In Repair', 'Faulty') NOT NULL,
  CONSTRAINT fk_cpus_device FOREIGN KEY (device_id) REFERENCES devices (device_id)
);

CREATE TABLE IF NOT EXISTS printers (
  printer_id INT PRIMARY KEY AUTO_INCREMENT,
  device_id VARCHAR(20) NOT NULL,
  printer_model VARCHAR(100) NOT NULL,
  printer_type ENUM('Inkjet', 'Laser', 'Dot Matrix') NOT NULL,
  color_capability ENUM('Color', 'Black & White') NOT NULL,
  connectivity ENUM('USB', 'Network', 'Wireless') NOT NULL,
  serial_number VARCHAR(100),
  CONSTRAINT fk_printers_device FOREIGN KEY (device_id) REFERENCES devices (device_id)
);

CREATE TABLE IF NOT EXISTS grievances (
  grievance_id INT PRIMARY KEY AUTO_INCREMENT,
  submitted_by VARCHAR(100) NOT NULL,
  stud_enrollment VARCHAR(20) NOT NULL,
  class VARCHAR(10) NOT NULL,
  lab_id VARCHAR(20) NOT NULL,
  device_id VARCHAR(20),
  device_category ENUM('PC', 'Printer', 'Mouse', 'Keyboard', 'Monitor', 'CPU') NOT NULL,
  device_name VARCHAR(100),
  submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Submitted', 'In Progress', 'Under Review', 'Resolved', 'Closed') DEFAULT 'Submitted',
  stud_email VARCHAR(100) NOT NULL,
  grievance_desc TEXT NOT NULL,
  admin_note TEXT,
  CONSTRAINT fk_grievances_lab FOREIGN KEY (lab_id) REFERENCES labs (lab_id),
  CONSTRAINT fk_grievances_device FOREIGN KEY (device_id) REFERENCES devices (device_id)
);
