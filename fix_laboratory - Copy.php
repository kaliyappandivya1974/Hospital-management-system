<?php
/**
 * AKIRA HOSPITAL Management System
 * Laboratory Management System
 * Database fix script
 */

// Include database connection if not already included
if (!isset($pdo)) {
    require_once 'db_connect.php';
}

// Only show HTML output if script is accessed directly
$is_direct_access = (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__));

if ($is_direct_access) {
    echo "<h2>AKIRA HOSPITAL - Laboratory Database Fix</h2>";
    echo "<pre>";
}

function log_message($message) {
    global $is_direct_access;
    if ($is_direct_access) {
        echo $message . "\n";
    }
}

// Step 1: Create lab_departments table if it doesn't exist
log_message("Creating lab_departments table...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lab_departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active'
        )
    ");
    log_message("- lab_departments table created or already exists");
    
    // Add default departments if needed
    $check = $pdo->query("SELECT COUNT(*) FROM lab_departments")->fetchColumn();
    if ($check == 0) {
        log_message("- Adding default lab departments");
        $dept_data = [
            ['name' => 'Hematology', 'description' => 'Blood testing department'],
            ['name' => 'Biochemistry', 'description' => 'Chemical analysis department'],
            ['name' => 'Radiology', 'description' => 'Medical imaging department'],
            ['name' => 'Microbiology', 'description' => 'Microorganism analysis'],
            ['name' => 'Pathology', 'description' => 'Tissue and sample analysis']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO lab_departments (name, description) VALUES (?, ?)");
        foreach ($dept_data as $dept) {
            $stmt->execute([$dept['name'], $dept['description']]);
        }
        log_message("- Added " . count($dept_data) . " default departments");
    } else {
        log_message("- Lab departments already exist, skipping defaults");
    }
} catch (PDOException $e) {
    log_message("Error creating lab_departments: " . $e->getMessage());
    throw $e;
}

// Step 2: Create lab_tests table
log_message("\nCreating lab_tests table...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lab_tests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            lab_department_id INT NOT NULL,
            cost DECIMAL(10,2) NOT NULL DEFAULT 0,
            description TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            FOREIGN KEY (lab_department_id) REFERENCES lab_departments(id) ON DELETE CASCADE
        )
    ");
    log_message("- lab_tests table created or already exists");
    
    // Add sample lab tests if table is empty
    $check = $pdo->query("SELECT COUNT(*) FROM lab_tests")->fetchColumn();
    if ($check == 0) {
        log_message("- Adding sample lab tests");
        $tests_data = [
            ['name' => 'Complete Blood Count (CBC)', 'lab_department_id' => 1, 'cost' => 250.00, 'description' => 'Analyzes different components of blood'],
            ['name' => 'Lipid Profile', 'lab_department_id' => 2, 'cost' => 500.00, 'description' => 'Measures cholesterol and triglycerides'],
            ['name' => 'Blood Glucose', 'lab_department_id' => 2, 'cost' => 300.00, 'description' => 'Measures blood sugar levels'],
            ['name' => 'Liver Function Test', 'lab_department_id' => 2, 'cost' => 1200.00, 'description' => 'Assesses liver function and damage'],
            ['name' => 'Kidney Function Test', 'lab_department_id' => 2, 'cost' => 1000.00, 'description' => 'Evaluates kidney function'],
            ['name' => 'Urinalysis', 'lab_department_id' => 1, 'cost' => 250.00, 'description' => 'Physical, chemical and microscopic examination of urine'],
            ['name' => 'Chest X-Ray', 'lab_department_id' => 3, 'cost' => 1500.00, 'description' => 'Imaging of chest, heart and lungs'],
            ['name' => 'MRI Brain', 'lab_department_id' => 3, 'cost' => 4000.00, 'description' => 'Detailed imaging of the brain']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO lab_tests (name, lab_department_id, cost, description, status) VALUES (?, ?, ?, ?, 'active')");
        foreach ($tests_data as $test) {
            $stmt->execute([$test['name'], $test['lab_department_id'], $test['cost'], $test['description']]);
        }
        log_message("- Added " . count($tests_data) . " sample lab tests");
    }
} catch (PDOException $e) {
    log_message("Error creating lab_tests: " . $e->getMessage());
    throw $e;
}

// Step 3: Create patient_lab_tests table
log_message("\nCreating patient_lab_tests table...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patient_lab_tests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            lab_test_id INT NOT NULL,
            requested_by INT NOT NULL,
            test_date DATE NOT NULL,
            results TEXT,
            result_date DATE,
            status ENUM('requested', 'in_progress', 'completed') DEFAULT 'requested',
            technician_id INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (lab_test_id) REFERENCES lab_tests(id) ON DELETE CASCADE,
            FOREIGN KEY (requested_by) REFERENCES doctors(id) ON DELETE CASCADE,
            FOREIGN KEY (technician_id) REFERENCES staff(id) ON DELETE SET NULL
        )
    ");
    log_message("- patient_lab_tests table created or already exists");
} catch (PDOException $e) {
    log_message("Error creating patient_lab_tests: " . $e->getMessage());
    throw $e;
}

log_message("\nLaboratory database fix completed!");

if ($is_direct_access) {
    echo "</pre>";
    echo "<p><a href='laboratory.php' class='btn btn-primary'>Go to Laboratory Module</a></p>";
}
?>