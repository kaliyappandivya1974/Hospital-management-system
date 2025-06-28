<?php
/**
 * AKIRA HOSPITAL Management System
 * Fix Billing Tables Web Script
 */

// Set headers for better output formatting
header('Content-Type: text/plain');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db_connect.php';

try {
    echo "Starting database fix...\n\n";

    // Drop existing tables if they exist (in correct order due to foreign keys)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DROP TABLE IF EXISTS invoice_items");
    $pdo->exec("DROP TABLE IF EXISTS invoices");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Dropped existing tables.\n";
    
    // Create invoices table with correct structure
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoices (
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
    ");
    
    echo "Created invoices table.\n";
    
    // Create invoice_items table with correct structure
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_id INT NULL,
            description TEXT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10, 2) NOT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "Created invoice_items table.\n";

    // Verify table structures
    echo "\nVerifying table structures:\n";
    
    // Check invoices table columns
    $columns = $pdo->query("SHOW COLUMNS FROM invoices");
    echo "\nInvoices table columns:\n";
    while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$col['Field']}: {$col['Type']}\n";
    }
    
    // Check invoice_items table columns
    $columns = $pdo->query("SHOW COLUMNS FROM invoice_items");
    echo "\nInvoice items table columns:\n";
    while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$col['Field']}: {$col['Type']}\n";
    }

    echo "\nDatabase fix completed successfully!\n";
    echo "\nYou can now go back to the billing page and try creating/editing invoices.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e->errorInfo[1]) {
        echo "Error Code: " . $e->errorInfo[1] . "\n";
        echo "SQL State: " . $e->errorInfo[0] . "\n";
    }
} 