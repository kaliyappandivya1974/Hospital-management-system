<?php
/**
 * AKIRA HOSPITAL Management System
 * XAMPP Compatibility Helper
 * 
 * This file provides helper functions to ensure database operations
 * work properly in XAMPP/MySQL environment
 */

// Include database connection
require_once 'db_connect.php';

/**
 * Performs direct PDO query - use this instead of db_query in XAMPP
 * 
 * @param string $sql SQL query with named parameters
 * @param array $params Array of parameters
 * @return PDOStatement|false The result set
 */
function pdo_query($sql, $params = []) {
    global $pdo;
    
    if (!$pdo) {
        die("Database connection failed. Check db_connect.php");
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        // Log error
        error_log("Database error: " . $e->getMessage());
        // Return false to indicate failure
        return false;
    }
}

/**
 * Creates a new invoice using PDO and returns the new ID
 * 
 * @param int $patientId Patient ID
 * @param string $invoiceDate Invoice date
 * @param float $amount Total amount
 * @param string $status Status
 * @return int|false New invoice ID or false on failure
 */
function create_invoice_pdo($patientId, $invoiceDate, $amount, $status) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO invoices (patient_id, invoice_date, total_amount, status) 
                VALUES (:patient_id, :invoice_date, :total_amount, :status)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':patient_id' => $patientId,
            ':invoice_date' => $invoiceDate,
            ':total_amount' => $amount,
            ':status' => $status
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Invoice creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Creates a new invoice item using PDO
 * 
 * @param int $invoiceId Invoice ID
 * @param string $description Item description
 * @param float $amount Item amount
 * @return bool Success status
 */
function create_invoice_item_pdo($invoiceId, $description, $amount) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO invoice_items (invoice_id, description, amount) 
                VALUES (:invoice_id, :description, :amount)";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':description' => $description,
            ':amount' => $amount
        ]);
        
        return $success;
    } catch (PDOException $e) {
        error_log("Invoice item creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes an invoice and its items using PDO transaction
 * 
 * @param int $invoiceId Invoice ID to delete
 * @return bool Success status
 */
function delete_invoice_pdo($invoiceId) {
    global $pdo;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete invoice items first
        $deleteItemsStmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = :id");
        $deleteItemsStmt->execute([':id' => $invoiceId]);
        
        // Delete invoice
        $deleteInvoiceStmt = $pdo->prepare("DELETE FROM invoices WHERE id = :id");
        $deleteInvoiceStmt->execute([':id' => $invoiceId]);
        
        // Commit transaction
        $pdo->commit();
        
        return true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Invoice deletion error: " . $e->getMessage());
        return false;
    }
}

/**
 * Creates a new doctor using PDO and returns the new ID
 * 
 * @param array $doctorData Array containing doctor data
 * @return int|false New doctor ID or false on failure
 */
function create_doctor_pdo($doctorData) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO doctors (name, department_id, specialization, email, phone, address, experience, status) 
                VALUES (:name, :department_id, :specialization, :email, :phone, :address, :experience, :status)";
        
        $params = [
            ':name' => $doctorData['name'] ?? '',
            ':department_id' => $doctorData['department_id'] ?? null,
            ':specialization' => $doctorData['specialization'] ?? '',
            ':email' => $doctorData['email'] ?? '',
            ':phone' => $doctorData['phone'] ?? '',
            ':address' => $doctorData['address'] ?? '',
            ':experience' => $doctorData['qualification'] ?? '', // Using qualification as experience
            ':status' => 'active'
        ];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Doctor creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates an existing doctor using PDO
 * 
 * @param int $doctorId Doctor ID to update
 * @param array $doctorData Array containing doctor data
 * @return bool Success status
 */
function update_doctor_pdo($doctorId, $doctorData) {
    global $pdo;
    
    try {
        // Check the existing columns in the doctors table
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM doctors LIKE 'qualification'");
            $hasQualificationCol = $colCheck->rowCount() > 0;
            
            $colCheck = $pdo->query("SHOW COLUMNS FROM doctors LIKE 'joining_date'");
            $hasJoiningDateCol = $colCheck->rowCount() > 0;
            
            $colCheck = $pdo->query("SHOW COLUMNS FROM doctors LIKE 'experience'");
            $hasExperienceCol = $colCheck->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Column check error: " . $e->getMessage());
            // Default to old structure if can't check
            $hasQualificationCol = true;
            $hasJoiningDateCol = true;
            $hasExperienceCol = false;
        }
        
        // Build SQL based on available columns
        $sql = "UPDATE doctors SET 
                name = :name, 
                department_id = :department_id, 
                specialization = :specialization, 
                email = :email, 
                phone = :phone, 
                address = :address, ";
                
        if ($hasQualificationCol) {
            $sql .= "qualification = :qualification, ";
        }
        if ($hasJoiningDateCol) {
            $sql .= "joining_date = :joining_date, ";
        }
        if ($hasExperienceCol) {
            $sql .= "experience = :experience, ";
        }
        
        $sql .= "status = :status,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        $params = [
            ':name' => $doctorData['name'] ?? '',
            ':department_id' => $doctorData['department_id'] ?? null,
            ':specialization' => $doctorData['specialization'] ?? '',
            ':email' => $doctorData['email'] ?? '',
            ':phone' => $doctorData['phone'] ?? '',
            ':address' => $doctorData['address'] ?? '',
            ':status' => $doctorData['status'] ?? 'active',
            ':id' => $doctorId
        ];
        
        // Add parameters based on available columns
        if ($hasQualificationCol) {
            $params[':qualification'] = $doctorData['qualification'] ?? '';
        }
        if ($hasJoiningDateCol) {
            $params[':joining_date'] = $doctorData['joining_date'] ?? null;
        }
        if ($hasExperienceCol) {
            $params[':experience'] = $doctorData['qualification'] ?? ''; // Using qualification as experience
        }
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Doctor update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes a doctor record using PDO
 * 
 * @param int $doctorId Doctor ID to delete
 * @return bool Success status
 */
function delete_doctor_pdo($doctorId) {
    global $pdo;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // First delete related schedules if any
        $deleteSchedulesStmt = $pdo->prepare("DELETE FROM doctor_schedules WHERE doctor_id = :id");
        $deleteSchedulesStmt->execute([':id' => $doctorId]);
        
        // Delete the doctor
        $deleteDoctorStmt = $pdo->prepare("DELETE FROM doctors WHERE id = :id");
        $deleteDoctorStmt->execute([':id' => $doctorId]);
        
        // Commit transaction
        $pdo->commit();
        
        return true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Doctor deletion error: " . $e->getMessage());
        return false;
    }
}