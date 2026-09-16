# CarePlus Smart Hospital Management System (HMS)

CarePlus is a full-stack, enterprise-grade Hospital Management System designed for healthcare institutions. Built with native **PHP 8.2+**, **MySQL 8.0+**, **Bootstrap 5**, and **Chart.js**, it operates seamlessly without external runtime framework dependencies.

---

## Technical Stack Architecture

- **Backend Logic Engine:** PHP 8.2+ (PDO, Object-Oriented Prepared Statements)
- **Database Engine:** MySQL 8.0+ / MariaDB (InnoDB Storage Engine with strict FK constraints)
- **Frontend Stack:** HTML5, CSS3, JavaScript (Vanilla ES6 AJAX / Fetch APIs)
- **UI Component Framework:** Bootstrap 5.3, Bootstrap Icons
- **Data Visualizations:** Chart.js

---

## Prerequisites & Server Requirements

- **XAMPP Server** (PHP 8.2 or higher enabled)
- **MySQL Engine** 8.0+ / MariaDB 10.4+
- `pdo_mysql` PHP extension enabled
- `mbstring` and `gd` extensions enabled for image upload processing

---

## Installation & Deployment Instructions

### Step 1: Directory Setup
Extract the project folder into your local XAMPP webserver root:
`C:\xampp\htdocs\hospital-system\`

### Step 2: Database Creation & Seeding
1. Launch **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a database named `smart_hospital` with collation `utf8mb4_unicode_ci`.
3. Import the complete SQL schema and sample dataset provided in `database.sql`.

### Step 3: Global Configuration Tuning
Verify base directory configuration settings inside `config/config.php`:
```php
define('BASE_URL', 'http://localhost/hospital-system/');