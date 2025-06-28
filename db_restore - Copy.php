<?php
/**
 * AKIRA HOSPITAL Management System
 * Database Restore Script
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Only admin users can perform restore
if ($_SESSION['admin_role'] !== 'admin') {
    $_SESSION['error'] = "You don't have permission to restore database backups.";
    header("Location: settings.php?section=database");
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Set error/success messages
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    try {
        // Check if file was uploaded
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("No backup file uploaded or upload error");
        }
        
        // Validate file
        $file_tmp = $_FILES['backup_file']['tmp_name'];
        $file_name = $_FILES['backup_file']['name'];
        $file_size = $_FILES['backup_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check extension
        if ($file_ext !== 'sql') {
            throw new Exception("Invalid file format. Only SQL files are allowed.");
        }
        
        // Check size (max 50MB)
        if ($file_size > 50 * 1024 * 1024) {
            throw new Exception("File size exceeds the limit (50MB)");
        }
        
        // Read file content
        $sql_content = file_get_contents($file_tmp);
        if ($sql_content === false) {
            throw new Exception("Failed to read the uploaded file");
        }
        
        // Split into separate SQL statements
        $sql_statements = preg_split('/;\s*\n/', $sql_content);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Execute each statement
        foreach ($sql_statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success = "Database restored successfully";
        $_SESSION['success'] = $success;
        header("Location: settings.php?section=database");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $error = "Restore failed: " . $e->getMessage();
        $_SESSION['error'] = $error;
        header("Location: settings.php?section=database");
        exit;
    }
} else {
    // Redirect back to settings page
    header("Location: settings.php?section=database");
    exit;
}