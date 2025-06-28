<?php
/**
 * AKIRA HOSPITAL Management System
 * Database Backup Script
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Only admin users can perform backups
if ($_SESSION['admin_role'] !== 'admin') {
    $_SESSION['error'] = "You don't have permission to perform database backups.";
    header("Location: settings.php?section=database");
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Set error/success messages
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        // Create backup directory if it doesn't exist
        $backup_dir = 'backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        // Generate a filename with timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $filename = $backup_dir . '/akira_hospital_backup_' . $timestamp . '.sql';
        
        // Check if we have database connection info
        if (!isset($db_host) || !isset($db_name) || !isset($db_user) || !isset($db_pass)) {
            throw new Exception("Database connection information is missing");
        }
        
        // Get tables
        $tables = [];
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tables[] = $row['table_name'];
        }
        
        // Start output buffering
        ob_start();
        
        // Add header and timestamp
        echo "-- AKIRA HOSPITAL Database Backup\n";
        echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        echo "-- ------------------------------------------------------\n\n";
        
        // For each table
        foreach ($tables as $table) {
            echo "-- Table structure for table `$table`\n";
            echo "DROP TABLE IF EXISTS `$table` CASCADE;\n";
            
            // Get create table statement
            $stmt = $pdo->query("SELECT pg_get_tabledef('$table') AS create_table");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo $row['create_table'] . ";\n\n";
            
            // Get table data
            $data = $pdo->query("SELECT * FROM $table");
            $rows = $data->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                echo "-- Dumping data for table `$table`\n";
                
                // Get column names
                $columns = array_keys($rows[0]);
                
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote($value);
                        }
                    }
                    
                    echo "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                
                echo "\n";
            }
        }
        
        // Get buffer contents
        $sql = ob_get_clean();
        
        // Write to file
        if (file_put_contents($filename, $sql) === false) {
            throw new Exception("Failed to write backup file");
        }
        
        // Generate download headers
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        readfile($filename);
        exit;
        
    } catch (Exception $e) {
        $error = "Backup failed: " . $e->getMessage();
        $_SESSION['error'] = $error;
        header("Location: settings.php?section=database");
        exit;
    }
} else {
    // Redirect back to settings page
    header("Location: settings.php?section=database");
    exit;
}