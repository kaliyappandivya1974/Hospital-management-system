<?php
/**
 * AKIRA HOSPITAL Management System
 * Add Laboratory Test Order
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Set error/success messages
$error = null;
$success = null;

// Get list of patients
$patients = [];
try {
    // Check if medical_record_number column exists
    $check_column = $pdo->query("SELECT COUNT(*) as count FROM information_schema.columns 
                               WHERE table_name = 'patients' AND column_name = 'medical_record_number'");
    $has_mrn = $check_column->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    // Query with or without the medical_record_number column
    if ($has_mrn) {
        $stmt = $pdo->query("SELECT id, name, medical_record_number FROM patients ORDER BY name");
    } else {
        $stmt = $pdo->query("SELECT id, name FROM patients ORDER BY name");
    }
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching patients: " . $e->getMessage();
}

// Get list of doctors
$doctors = [];
try {
    $stmt = $pdo->query("SELECT id, name, specialization FROM doctors ORDER BY name");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching doctors: " . $e->getMessage();
}

// Get list of test types
$test_types = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'lab_tests'")) {
        // Get lab tests with their department names
        $test_types = db_get_rows("
            SELECT t.id, t.name, t.cost as price, t.description, t.status,
                   d.name as category 
            FROM lab_tests t
            LEFT JOIN lab_departments d ON t.lab_department_id = d.id
            WHERE t.status = 'active'
            ORDER BY d.name, t.name
        ");
    } else {
        // Create lab_tests table if it doesn't exist
        db_query("
            CREATE TABLE IF NOT EXISTS lab_tests (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                lab_department_id INT NULL,
                cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                description TEXT NULL,
                status VARCHAR(20) DEFAULT 'active'
            )
        ");
        
        // Create lab_departments table if it doesn't exist
        db_query("
            CREATE TABLE IF NOT EXISTS lab_departments (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT NULL
            )
        ");
        
        // Add default departments if needed
        $default_departments = [
            ['name' => 'Hematology', 'description' => 'Blood tests'],
            ['name' => 'Chemistry', 'description' => 'Chemical tests'], 
            ['name' => 'Radiology', 'description' => 'Imaging tests']
        ];
        
        foreach ($default_departments as $dept) {
            if (!db_get_row("SELECT 1 FROM lab_departments WHERE name = :name", [':name' => $dept['name']])) {
                db_insert('lab_departments', $dept);
            }
        }
        
        // Get department IDs
        $hematology_id = db_get_row("SELECT id FROM lab_departments WHERE name = 'Hematology'")['id'] ?? 1;
        $chemistry_id = db_get_row("SELECT id FROM lab_departments WHERE name = 'Chemistry'")['id'] ?? 2;
        $radiology_id = db_get_row("SELECT id FROM lab_departments WHERE name = 'Radiology'")['id'] ?? 3;
        
        // Add some default test types
        $default_tests = [
            ['name' => 'Complete Blood Count (CBC)', 'lab_department_id' => $hematology_id, 'cost' => 500.00, 'status' => 'active'],
            ['name' => 'Blood Glucose', 'lab_department_id' => $chemistry_id, 'cost' => 300.00, 'status' => 'active'],
            ['name' => 'Lipid Profile', 'lab_department_id' => $chemistry_id, 'cost' => 800.00, 'status' => 'active'],
            ['name' => 'Liver Function Test', 'lab_department_id' => $chemistry_id, 'cost' => 1200.00, 'status' => 'active'],
            ['name' => 'Kidney Function Test', 'lab_department_id' => $chemistry_id, 'cost' => 1000.00, 'status' => 'active'],
            ['name' => 'Urinalysis', 'lab_department_id' => $chemistry_id, 'cost' => 250.00, 'status' => 'active'],
            ['name' => 'Chest X-Ray', 'lab_department_id' => $radiology_id, 'cost' => 1500.00, 'status' => 'active']
        ];
        
        foreach ($default_tests as $test) {
            db_insert('lab_tests', $test);
        }
        
        // Fetch the newly created test types
        $test_types = db_get_rows("
            SELECT t.id, t.name, t.cost as price, t.description, t.status,
                   d.name as category 
            FROM lab_tests t
            LEFT JOIN lab_departments d ON t.lab_department_id = d.id
            WHERE t.status = 'active'
            ORDER BY d.name, t.name
        ");
    }
} catch (PDOException $e) {
    $error = "Error with test types: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    try {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $doctor_id = !empty($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;
        $test_id = intval($_POST['test_id'] ?? 0);
        $order_date = $_POST['order_date'] ?? date('Y-m-d');
        $order_time = $_POST['order_time'] ?? date('H:i:s');
        $notes = $_POST['notes'] ?? '';
        
        // Validation
        if ($patient_id <= 0) {
            throw new Exception("Patient is required");
        }
        if ($test_id <= 0) {
            throw new Exception("Test type is required");
        }
        
        // Make sure the lab_orders table exists
        if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'lab_orders'")) {
            // Create lab_orders table if it doesn't exist
            db_query("
                CREATE TABLE IF NOT EXISTS lab_orders (
                    id SERIAL PRIMARY KEY,
                    patient_id INT NOT NULL,
                    doctor_id INT NULL,
                    test_id INT NOT NULL,
                    order_date DATE NOT NULL,
                    order_time TIME NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    results TEXT NULL,
                    normal_values TEXT NULL,
                    completed_at TIMESTAMP NULL,
                    notes TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
                    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
                    FOREIGN KEY (test_id) REFERENCES lab_tests(id) ON DELETE RESTRICT
                )
            ");
        }
        
        // Verify referenced entities exist
        $patient_exists = db_get_row("SELECT 1 FROM patients WHERE id = :id", [':id' => $patient_id]);
        if (!$patient_exists) {
            throw new Exception("Selected patient doesn't exist in the database");
        }
        
        if ($doctor_id) {
            $doctor_exists = db_get_row("SELECT 1 FROM doctors WHERE id = :id", [':id' => $doctor_id]);
            if (!$doctor_exists) {
                throw new Exception("Selected doctor doesn't exist in the database");
            }
        }
        
        $test_exists = db_get_row("SELECT 1 FROM lab_tests WHERE id = :id", [':id' => $test_id]);
        if (!$test_exists) {
            throw new Exception("Selected test type doesn't exist in the database");
        }
        
        // Insert the order
        $order_data = [
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'test_id' => $test_id,
            'order_date' => $order_date,
            'order_time' => $order_time,
            'notes' => $notes,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $order_id = db_insert('lab_orders', $order_data);
        
        if ($order_id) {
            $success = "Lab order created successfully with ID: $order_id";
            // Redirect to laboratory page
            header("Location: laboratory.php?action=view_order&order_id=$order_id");
            exit;
        } else {
            throw new Exception("Failed to create lab order");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Create Laboratory Test Order</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="laboratory.php" class="btn btn-sm btn-outline-secondary">Back to Laboratory</a>
                    </div>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success!</strong> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">New Test Order</h5>
                    
                    <form action="add_lab_order.php" method="post">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                                <select class="form-control" id="patient_id" name="patient_id" required>
                                    <option value="">-- Select Patient --</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['name']); ?> <?php echo !empty($patient['medical_record_number']) ? '(MRN: ' . htmlspecialchars($patient['medical_record_number']) . ')' : ''; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="doctor_id" class="form-label">Referring Doctor</label>
                                <select class="form-control" id="doctor_id" name="doctor_id">
                                    <option value="">-- Select Doctor (Optional) --</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?> (<?php echo htmlspecialchars($doctor['specialization']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="test_id" class="form-label">Test Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="test_id" name="test_id" required>
                                    <option value="">-- Select Test --</option>
                                    <?php 
                                    $current_category = '';
                                    foreach ($test_types as $test): 
                                        if (isset($test['category']) && $current_category != $test['category']) {
                                            if ($current_category != '') {
                                                echo '</optgroup>';
                                            }
                                            $current_category = $test['category'];
                                            if (!empty($current_category)) {
                                                echo '<optgroup label="' . htmlspecialchars($current_category) . '">';
                                            }
                                        }
                                    ?>
                                        <option value="<?php echo $test['id']; ?>"><?php echo htmlspecialchars($test['name']); ?> - ₹<?php echo number_format(isset($test['price']) ? $test['price'] : $test['cost'], 2); ?></option>
                                    <?php 
                                    endforeach; 
                                    if ($current_category != '') {
                                        echo '</optgroup>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="order_date" class="form-label">Order Date</label>
                                <input type="date" class="form-control" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="order_time" class="form-label">Order Time</label>
                                <input type="time" class="form-control" id="order_time" name="order_time" value="<?php echo date('H:i'); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <button type="submit" name="submit_order" class="btn btn-primary">Create Order</button>
                            <a href="laboratory.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Bootstrap JS and other scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Add any JavaScript needed for this page
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize any plugins or form validation
    });
</script>
</body>
</html>