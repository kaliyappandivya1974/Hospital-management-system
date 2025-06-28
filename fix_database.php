<?php
/**
 * AKIRA HOSPITAL Management System
 * Database Fix Script
 */

// Include database connection
require_once 'db_connect.php';

try {
    // Check if invoices table exists
    $table_exists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'invoices'")->fetchColumn();
    
    if ($table_exists) {
        // Check if due_date column exists
        $column_exists = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'due_date'")->rowCount() > 0;
        
        if (!$column_exists) {
            // Add due_date column
            $pdo->exec("ALTER TABLE invoices ADD COLUMN due_date DATE NULL AFTER invoice_date");
            echo "Successfully added due_date column to invoices table.\n";
        } else {
            echo "due_date column already exists.\n";
        }
    } else {
        echo "invoices table does not exist. Please run the main application first to create it.\n";
    }
    
    echo "Database check completed.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 