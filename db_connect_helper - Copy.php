<?php
/**
 * AKIRA HOSPITAL Management System
 * Database Connection Helper
 * 
 * This file provides compatibility functions to work across
 * different environments (Replit, XAMPP)
 */

// Check if PDO is already defined in db_connect.php
if (!isset($pdo)) {
    // Define database variables
    $db_host = 'localhost';
    $db_name = 'akira_hospital';
    $db_user = 'root';
    $db_pass = '';
    
    // Try to connect
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

/**
 * Function to execute a database query using PDO
 */
if (!function_exists('db_query')) {
    function db_query($query, $params = []) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Function to get a single row from a query
 */
if (!function_exists('db_get_row')) {
    function db_get_row($query, $params = []) {
        $stmt = db_query($query, $params);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }
}

/**
 * Function to get all rows from a query
 */
if (!function_exists('db_get_all')) {
    function db_get_all($query, $params = []) {
        $stmt = db_query($query, $params);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }
}

/**
 * Function to get the last inserted ID
 */
if (!function_exists('db_last_insert_id')) {
    function db_last_insert_id() {
        global $pdo;
        return $pdo->lastInsertId();
    }
}