CREATE TABLE `labs` (
  `lab_id` varchar(20) PRIMARY KEY,
  `lab_name` varchar(100) NOT NULL,
  `status` enum('Active', 'InActive', 'In Repair') NOT NULL,
  `department` varchar(100) NOT NULL,
  `lab_incharge` varchar(100) NOT NULL,
  `establishment_date` date NOT NULL,
  `room_capacity` int NOT NULL,
  `building` varchar(100),
  `room_number` varchar(50) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `users` (
  `user_id` varchar(20) PRIMARY KEY,
  `username` varchar(50) UNIQUE NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) UNIQUE NOT NULL,
  `user_type` enum('Admin', 'Incharge') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact_number` varchar(20),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `devices` (
  `device_id` varchar(20) PRIMARY KEY,
  `lab_id` varchar(20) NOT NULL,
  `device_type` enum('PC', 'Printer', 'Mouse', 'Keyboard', 'Monitor', 'CPU') NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `serial_number` varchar(100),
  `status` enum('Active', 'InActive', 'Under Repair') NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `pc_details` (
  `pc_id` int PRIMARY KEY AUTO_INCREMENT,
  `device_id` varchar(20) NOT NULL,
  `processor` varchar(100) NOT NULL,
  `ram` varchar(50) NOT NULL,
  `storage` varchar(100) NOT NULL,
  `operating_system` varchar(100) NOT NULL,
  `ethernet_mac` varchar(100),
  `wifi_adapter` varchar(100),
  `ip_address` varchar(50)
);

CREATE TABLE `monitors` (
  `monitor_id` int PRIMARY KEY AUTO_INCREMENT,
  `device_id` varchar(20) NOT NULL,
  `brand_model` varchar(100) NOT NULL,
  `resolution` varchar(50) NOT NULL,
  `serial_number` varchar(100),
  `status` enum('Active', 'In Repair', 'Faulty') NOT NULL
);

CREATE TABLE `keyboards` (
  `keyboard_id` int PRIMARY KEY AUTO_INCREMENT,
  `device_id` varchar(20) NOT NULL,
  `keyboard_name` varchar(100),
  `keyboard_type` enum('Wired', 'Wireless', 'Mechanical', 'Membrane') NOT NULL,
  `serial_number` varchar(100),
  `status` enum('Active', 'In Repair', 'Faulty') NOT NULL
);

CREATE TABLE `mice` (
  `mouse_id` int PRIMARY KEY AUTO_INCREMENT,
  `device_id` varchar(20) NOT NULL,
  `mouse_name` varchar(100),
  `mouse_type` enum('Wired', 'Wireless') NOT NULL,
  `serial_number` varchar(100),
  `status` enum('Active', 'In Repair', 'Faulty') NOT NULL
);

CREATE TABLE `cpus` (
  `cpu_id` int PRIMARY KEY AUTO_INCREMENT,
  `device_id` varchar(20) NOT NULL,
  `case_model` varchar(100),
  `serial_number` varchar(100),
  `power_supply` varchar(50) NOT NULL,
  `status` enum('Active', 'In Repair', 'Faulty') NOT NULL
);

CREATE TABLE `printers` (
  `printer_id` int PRIMARY KEY AUTO_INCREMENT,
  `device_id` varchar(20) NOT NULL,
  `printer_model` varchar(100) NOT NULL,
  `printer_type` enum('Inkjet', 'Laser', 'Dot Matrix') NOT NULL,
  `color_capability` enum('Color', 'Black & White') NOT NULL,
  `connectivity` enum('USB', 'Network', 'Wireless') NOT NULL,
  `serial_number` varchar(100)
);

ALTER TABLE `devices` ADD FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`);

ALTER TABLE `pc_details` ADD FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`);

ALTER TABLE `monitors` ADD FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`);

ALTER TABLE `keyboards` ADD FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`);

ALTER TABLE `mice` ADD FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`);

ALTER TABLE `cpus` ADD FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`);

ALTER TABLE `printers` ADD FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`);