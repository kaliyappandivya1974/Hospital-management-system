<?php
/**
 * AKIRA HOSPITAL Management System
 * Fix Billing Tables Script
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db_connect.php';

try {
    // First, check if we can connect to the database
    if (!$pdo) {
        throw new Exception("Database connection not established!");
    }
    
    // Test the connection
    $pdo->query("SELECT 1");
    
    echo "Connected to database successfully.\n";
    echo "Database type: " . $active_db_type . "\n";
    echo "Current database: " . $db_name . "\n\n";

    // Drop existing tables if they exist (in correct order due to foreign keys)
    echo "Disabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    echo "Dropping existing tables...\n";
    $pdo->exec("DROP TABLE IF EXISTS invoice_items");
    $pdo->exec("DROP TABLE IF EXISTS invoices");
    
    echo "Re-enabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Dropped existing tables.\n\n";
    
    // Create invoices table with correct structure
    echo "Creating invoices table...\n";
    $sql = "
        CREATE TABLE invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            generated_by INT NOT NULL,
            invoice_date DATE NOT NULL,
            due_date DATE NULL,
            total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
            discount DECIMAL(10, 2) DEFAULT 0,
            tax DECIMAL(10, 2) DEFAULT 0,
            grand_total DECIMAL(10, 2) NOT NULL DEFAULT 0,
            payment_status ENUM('pending', 'partially_paid', 'paid') DEFAULT 'pending',
            payment_method VARCHAR(50) NULL,
            payment_date DATE NULL,
            paid_amount DECIMAL(10, 2) DEFAULT 0,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (generated_by) REFERENCES admins(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    echo "Executing SQL:\n" . $sql . "\n\n";
    try {
        $pdo->exec($sql);
        echo "Invoices table created successfully.\n\n";
    } catch (PDOException $e) {
        echo "Error creating invoices table: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    // Create invoice_items table with correct structure
    echo "Creating invoice_items table...\n";
    $sql = "
        CREATE TABLE invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            item_type VARCHAR(50) NOT NULL DEFAULT 'service',
            item_id INT NULL,
            description TEXT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10, 2) NOT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    echo "Executing SQL:\n" . $sql . "\n\n";
    try {
        $pdo->exec($sql);
        echo "Invoice items table created successfully.\n\n";
    } catch (PDOException $e) {
        echo "Error creating invoice_items table: " . $e->getMessage() . "\n";
        throw $e;
    }

    // Verify table structures
    echo "Verifying table structures:\n";
    
    // Check invoices table columns
    echo "\nChecking invoices table columns:\n";
    try {
        $result = $pdo->query("SHOW CREATE TABLE invoices");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        echo $row['Create Table'] . "\n\n";
    } catch (PDOException $e) {
        echo "Error checking invoices table: " . $e->getMessage() . "\n";
    }
    
    // Check invoice_items table columns
    echo "\nChecking invoice_items table columns:\n";
    try {
        $result = $pdo->query("SHOW CREATE TABLE invoice_items");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        echo $row['Create Table'] . "\n\n";
    } catch (PDOException $e) {
        echo "Error checking invoice_items table: " . $e->getMessage() . "\n";
    }

    echo "Database fix completed successfully!\n";
    echo "You can now go back to the billing page and try creating/editing invoices.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e instanceof PDOException && $e->errorInfo[1]) {
        echo "Error Code: " . $e->errorInfo[1] . "\n";
        echo "SQL State: " . $e->errorInfo[0] . "\n";
    }
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
} 