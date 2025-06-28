<?php
/**
 * AKIRA HOSPITAL Management System
 * Database Maintenance Script
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Only admin users can perform maintenance
if ($_SESSION['admin_role'] !== 'admin') {
    $_SESSION['error'] = "You don't have permission to perform database maintenance.";
    header("Location: settings.php?section=database");
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Set error/success messages
$error = null;
$success = null;

// Handle clean old records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clean_old_records'])) {
    try {
        $clean_period = intval($_POST['clean_period'] ?? 12);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Calculate the date
        $cutoff_date = date('Y-m-d', strtotime("-{$clean_period} months"));
        
        // Tables to clean and their date columns
        $tables_to_clean = [
            'appointments' => 'appointment_date',
            'prescriptions' => 'created_at',
            'invoices' => 'invoice_date'
        ];
        
        $total_deleted = 0;
        
        foreach ($tables_to_clean as $table => $date_column) {
            // Check if table exists
            if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = '$table'")) {
                // Delete old records
                $sql = "DELETE FROM $table WHERE $date_column < :cutoff_date";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':cutoff_date' => $cutoff_date]);
                $total_deleted += $stmt->rowCount();
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success = "Successfully cleaned {$total_deleted} old records older than {$clean_period} months.";
        $_SESSION['success'] = $success;
        header("Location: settings.php?section=database");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $error = "Cleanup failed: " . $e->getMessage();
        $_SESSION['error'] = $error;
        header("Location: settings.php?section=database");
        exit;
    }
}

// Handle optimize database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['optimize_db'])) {
    try {
        // Get all tables
        $tables = [];
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tables[] = $row['table_name'];
        }
        
        // For PostgreSQL, VACUUM is the optimization command
        foreach ($tables as $table) {
            $pdo->exec("VACUUM ANALYZE $table");
        }
        
        $success = "Database optimization completed successfully on " . count($tables) . " tables.";
        $_SESSION['success'] = $success;
        header("Location: settings.php?section=database");
        exit;
        
    } catch (Exception $e) {
        $error = "Optimization failed: " . $e->getMessage();
        $_SESSION['error'] = $error;
        header("Location: settings.php?section=database");
        exit;
    }
}

// Handle database upgrade (for adding new columns to existing tables)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade_db'])) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        $upgrade_log = [];
        
        // Check if the lab_orders table exists
        if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'lab_orders'")) {
            // Check if normal_values column exists in lab_orders
            $column_exists = db_get_row("
                SELECT 1 
                FROM information_schema.columns 
                WHERE table_name = 'lab_orders' AND column_name = 'normal_values'
            ");
            
            // Add the column if it doesn't exist
            if (!$column_exists) {
                db_query("ALTER TABLE lab_orders ADD COLUMN IF NOT EXISTS normal_values TEXT NULL");
                $upgrade_log[] = "Added 'normal_values' column to lab_orders table";
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        if (count($upgrade_log) > 0) {
            $success = "Database upgrade completed successfully:<br>" . implode("<br>", $upgrade_log);
        } else {
            $success = "Database is already up to date, no upgrades needed.";
        }
        
        $_SESSION['success'] = $success;
        header("Location: settings.php?section=database");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $error = "Database upgrade failed: " . $e->getMessage();
        $_SESSION['error'] = $error;
        header("Location: settings.php?section=database");
        exit;
    }
} else {
    // Redirect back to settings page
    header("Location: settings.php?section=database");
    exit;
}