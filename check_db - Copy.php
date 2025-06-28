<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

try {
    // Check database connection
    echo "Checking database connection...\n";
    $pdo->query("SELECT 1");
    echo "Database connection successful!\n\n";

    // Check if invoices table exists
    echo "Checking invoices table...\n";
    $result = $pdo->query("SELECT COUNT(*) FROM invoices");
    echo "Invoices table exists and is accessible!\n";
    $count = $result->fetchColumn();
    echo "Number of invoices: " . $count . "\n\n";

    // Check table structure
    echo "Checking invoices table structure...\n";
    $result = $pdo->query("DESCRIBE invoices");
    echo "Table structure:\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    // If table doesn't exist, try to create it
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "\nAttempting to create invoices table...\n";
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS invoices (
                    id SERIAL PRIMARY KEY,
                    patient_id INT NOT NULL,
                    generated_by INT NOT NULL,
                    invoice_date DATE NOT NULL,
                    due_date DATE NULL,
                    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
                    discount DECIMAL(10, 2) DEFAULT 0,
                    tax DECIMAL(10, 2) DEFAULT 0,
                    grand_total DECIMAL(10, 2) NOT NULL DEFAULT 0,
                    payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    payment_method VARCHAR(50) NULL,
                    payment_date DATE NULL,
                    paid_amount DECIMAL(10, 2) DEFAULT 0,
                    notes TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
                    FOREIGN KEY (generated_by) REFERENCES admins(id)
                )
            ");
            echo "Successfully created invoices table!\n";
        } catch (PDOException $e2) {
            echo "Failed to create table: " . $e2->getMessage() . "\n";
        }
    }
} 