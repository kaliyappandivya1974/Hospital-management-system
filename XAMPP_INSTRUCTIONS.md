# AKIRA HOSPITAL Management System - XAMPP Deployment Guide

This document provides instructions for deploying and running the AKIRA HOSPITAL Management System using XAMPP.

## Prerequisites

1. XAMPP with PHP 7.4 or higher installed
2. MySQL database
3. Web browser (Chrome, Firefox, Edge recommended)

## Installation Steps

### Step 1: Download or Clone the Repository

Download all files from this repository and place them in the XAMPP `htdocs` folder, typically:
- Windows: `C:\xampp\htdocs\akira-hospital`
- macOS: `/Applications/XAMPP/htdocs/akira-hospital`
- Linux: `/opt/lampp/htdocs/akira-hospital`

### Step 2: Create the Database

1. Open your web browser and go to `http://localhost/phpmyadmin`
2. Create a new database named `hospital_db`
3. Import the database structure and data from `sql/hospital_db.sql` (if available)

### Step 3: Configure Database Connection

1. Check the `db_connect.php` file to ensure it's properly configured for XAMPP
2. Make sure the database connection details match your local setup:
   - Host: `localhost`
   - Database name: `hospital_db`
   - Username: `root`
   - Password: (your MySQL password, often empty by default in XAMPP)

### Step 4: Fix PDO-Related Issues

If you experience any issues with PDO or database connections:

1. Make sure the following helper files are included in your project:
   - `xampp_sync.php` - Provides XAMPP compatibility functions
   - `db_connect_helper.php` - Ensures consistent database connection

2. These helper files should already be properly referenced at the top of all PHP files that interact with the database.

### Step 5: Access the Application

1. Open your web browser and go to:
   - `http://localhost/akira-hospital` (if you placed files in an 'akira-hospital' subfolder)
   - or `http://localhost` (if you placed files directly in the 'htdocs' folder)

2. Log in with the default credentials:
   - Username: `admin`
   - Password: `admin123`

## Troubleshooting

### Database Connection Issues

If you encounter database connection errors:

1. Verify your MySQL service is running in the XAMPP Control Panel
2. Check the database name, username, and password in `db_connect.php`
3. Make sure the database exists and has the required tables

### PDO Statement Errors

If you see errors related to PDO statements or prepared statements:

1. Make sure the PHP PDO extension is enabled in your php.ini file
2. Verify that `xampp_sync.php` is included at the top of problematic PHP files

### Blank Pages or PHP Errors

1. Check XAMPP error logs at `xampp/logs/error.log`
2. Enable PHP error display in php.ini for debugging:
   - Set `display_errors = On`
   - Set `error_reporting = E_ALL`

## Maintenance and Updates

When updating the system with new files:

1. Always back up your database before making changes
2. Make sure to include both `xampp_sync.php` and `db_connect_helper.php` in your deployment
3. Check file permissions if running on Linux (should be readable by the web server)

## Contact

For any additional help or issues, please contact your system administrator.