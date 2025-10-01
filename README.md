# LabTrack
### Computer Lab Monitoring System

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue)](https://php.net)
[![GitHub Issues](https://img.shields.io/github/issues/Ompatil39/LabTrack)](https://github.com/Ompatil39/LabTrack/issues)

---

## Overview

LabTrack is a web-based laboratory monitoring and management system designed for educational institutions to streamline the administration of computer labs and their resources. Built with PHP, HTML, CSS, and JavaScript, the system provides role-based access for Admins and Lab In-charges to manage labs, track devices, and handle grievances efficiently. Admins can register labs with auto-generated lab codes, assign Lab In-charges, and maintain an updated inventory of devices including PCs, printers, and peripherals. The platform supports detailed device registration with specifications and quick entry features for faster management. A dedicated grievance management module allows students to report issues, while Admins and Lab In-charges can update statuses with automatic email notifications to keep students informed. With features like a dashboard for real-time insights, device search and filtering, and maintenance scheduling, LabTrack simplifies day-to-day operations while ensuring transparency and accountability in lab management.

---

## Demo Videos

### Complete System Walkthrough
<video src="images/LabTrack.mp4" controls></video>

### QR Code Feature Demo
<video src="images/QR.mp4" controls></video>



---

## Features

  **Role-Based Access Control**  
  - Admin: Manage labs, devices, grievances, and Lab In-charges.  
  - Lab In-charge: Oversee assigned labs, update device records, and handle grievances.  

  **Dashboard**  
  - Displays real-time metrics such as total labs, active devices, resolved grievances, and pending grievances.  

  **Lab Management**  
  - Register new labs with auto-generated lab codes.  
  - Assign Lab In-charges and maintain lab details.  

  **Device Management (Inventory)**  
  - Add and manage devices such as PCs, printers, and peripherals.  
  - Store detailed specifications including monitor, keyboard, mouse, and CPU details.  
  - "Copy Last Entry" option for quick registration of similar devices.  

  **QR Code Integration**  
  - **Automatic QR Generation**: Each device is automatically assigned a unique QR code for easy identification.  
  - **QR Code Scanning**: Built-in scanner allows users to scan a device's QR code and instantly navigate to its detailed page in the system.  
  - **PDF Export with QR Codes**: Generate comprehensive PDF reports containing:
    * Basic lab details (lab name, code, statistics)
    * Structured table listing all devices with their unique QR codes
    * Perfect for offline use, printing, and quick reference
  - This feature improves device management, reduces manual searching, and makes lab operations more efficient.

  **Grievance Management**  
  - Students can submit issues or complaints.  
  - Admins and Lab In-charges can update grievance statuses.  
  - Automatic email notifications sent to students when grievance status changes.  

  **Security**  
  - Password hashing, input sanitization, CSRF protection, and prepared statements to prevent SQL injection.  
  - Session-based authentication with role-level access control.  

---

## Requirements

- **PHP**: 7.4 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Extensions**: PDO, PDO_MySQL, JSON, CURL
- **Memory**: Minimum 512MB RAM
- **Storage**: At least 500MB free disk space

---

## Usage

### Accessing the System
1. Navigate to your configured URL (e.g., `http://localhost/LabTrack`)
2. Login with default credentials:
   - **Username**: admin
   - **Password**: admin123
3. Change default password immediately after first login

---

## Security

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection through input sanitization
- CSRF token validation
- Session-based authentication
- Role-based access control

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Support

For support and questions:
- **Issues**: [GitHub Issues](https://github.com/Ompatil39/LabTrack/issues)

---

**LabTrack** - Computer Lab Monitoring Made Simple

*Developed by Om Patil*
