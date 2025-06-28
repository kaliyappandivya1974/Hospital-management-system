<?php
/**
 * AKIRA HOSPITAL Management System
 * Doctors Management Page
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection and XAMPP compatibility helpers
require_once 'db_connect.php';
require_once 'xampp_sync.php'; // Include XAMPP compatibility helper

// Get admin information
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// Check if action is specified (new, edit, view, delete)
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Create doctors table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctors'")) {
        db_query("
            CREATE TABLE IF NOT EXISTS doctors (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                user_id INT NULL,
                department_id INT NULL,
                specialization VARCHAR(100) NULL,
                experience VARCHAR(255) NULL,
                email VARCHAR(100) NULL,
                phone VARCHAR(20) NULL,
                address TEXT NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
            )
        ");
        error_log("Created doctors table");
    }
} catch (PDOException $e) {
    error_log("Error creating doctors table: " . $e->getMessage());
}

// Get list of doctors
$doctors = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctors'")) {
        $doctors = db_get_rows("SELECT * FROM doctors ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
}

// Get departments for dropdowns
$departments = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'departments'")) {
        $departments = db_get_rows("SELECT * FROM departments ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Handle form submission for new/edit doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_doctor'])) {
    try {
        // Get and sanitize form data
        $name = trim($_POST['name'] ?? '');
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $specialization = trim($_POST['specialization'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $experience = !empty($_POST['experience']) ? intval($_POST['experience']) : 0;
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        $errors = [];
        if (empty($name)) $errors[] = "Doctor name is required";
        if (empty($department_id)) $errors[] = "Department is required";
        if (empty($specialization)) $errors[] = "Specialization is required";
        if (empty($qualification)) $errors[] = "Qualification is required";
        if (empty($phone)) $errors[] = "Phone number is required";
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (empty($errors)) {
            if ($action === 'new') {
                // Insert new doctor
                $stmt = $pdo->prepare("
                    INSERT INTO doctors (
                        name, department_id, specialization, qualification, 
                        experience, email, phone, address, status
                    ) VALUES (
                        ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?
                    )
                ");
                
                $result = $stmt->execute([
                    $name, $department_id, $specialization, $qualification,
                    $experience, $email, $phone, $address, $status
                ]);
                
                if (!$result) {
                    throw new PDOException("Failed to create doctor record");
                }
                
                $_SESSION['success_message'] = "Doctor added successfully";
                header("Location: doctors.php");
                exit;
            } elseif ($action === 'edit' && $doctor_id > 0) {
                // Update existing doctor using our helper function
                $doctorData = [
                    'name' => $name,
                    'department_id' => $department_id,
                    'specialization' => $specialization,
                    'qualification' => $qualification, // Will be used as experience in the helper function
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'status' => $status
                ];
                
                // Use the helper function from xampp_sync.php
                $update_success = update_doctor_pdo($doctor_id, $doctorData);
                
                if (!$update_success) {
                    throw new PDOException("Failed to update doctor record");
                }
                $success = "Doctor updated successfully";
                // Redirect to doctor list
                header("Location: doctors.php");
                exit;
            }
        } else {
            $error = implode("<br>", $errors);
        }
    } catch (PDOException $e) {
        error_log("Error in doctor form submission: " . $e->getMessage());
        $error = "Database error occurred. Please try again or contact support if the problem persists.";
    }
}

// Get doctor data for edit/view
$doctor = null;
if (($action === 'edit' || $action === 'view') && $doctor_id > 0) {
    try {
        $doctor = db_get_row("SELECT * FROM doctors WHERE id = :id", [':id' => $doctor_id]);
        if (!$doctor) {
            $error = "Doctor not found";
            $action = 'list'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'list'; // Fallback to list
    }
}

// Handle delete action
if ($action === 'delete' && $doctor_id > 0) {
    try {
        // Use our helper function for better XAMPP compatibility
        $delete_success = delete_doctor_pdo($doctor_id);
        
        if (!$delete_success) {
            throw new PDOException("Failed to delete doctor record");
        }
        
        $success = "Doctor deleted successfully";
        // Redirect to doctor list
        header("Location: doctors.php");
        exit;
    } catch (PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Create doctor_schedules table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctor_schedules'")) {
        db_query("
            CREATE TABLE IF NOT EXISTS doctor_schedules (
                id SERIAL PRIMARY KEY,
                doctor_id INT NOT NULL,
                day_of_week VARCHAR(10) NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                max_patients INT DEFAULT 20,
                is_available BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
            )
        ");
    }
} catch (PDOException $e) {
    error_log("Error creating doctor_schedules table: " . $e->getMessage());
}

// Handle doctor schedule form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    try {
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $days = $_POST['day'] ?? [];
        $start_times = $_POST['start_time'] ?? [];
        $end_times = $_POST['end_time'] ?? [];
        $max_patients = $_POST['max_patients'] ?? [];
        $is_available = $_POST['is_available'] ?? [];
        
        // Check if doctor exists
        if ($doctor_id <= 0) {
            $error = "Invalid doctor selected";
        } else {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete existing schedules for this doctor using direct PDO
            $deleteStmt = $pdo->prepare("DELETE FROM doctor_schedules WHERE doctor_id = :doctor_id");
            $deleteStmt->execute([':doctor_id' => $doctor_id]);
            
            // Add new schedules using direct PDO
            $insertStmt = $pdo->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, max_patients, is_available) 
                                          VALUES (:doctor_id, :day, :start_time, :end_time, :max_patients, :is_available)");
            
            for ($i = 0; $i < count($days); $i++) {
                if (!empty($days[$i]) && !empty($start_times[$i]) && !empty($end_times[$i])) {
                    $params = [
                        ':doctor_id' => $doctor_id,
                        ':day' => $days[$i],
                        ':start_time' => $start_times[$i],
                        ':end_time' => $end_times[$i],
                        ':max_patients' => intval($max_patients[$i] ?? 20),
                        ':is_available' => isset($is_available[$i]) ? true : false
                    ];
                    $insertStmt->execute($params);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Doctor schedule updated successfully";
            // Redirect to doctor schedule page
            header("Location: doctors.php?action=schedule&id={$doctor_id}");
            exit;
        }
    } catch (PDOException $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Database error: " . $e->getMessage();
    }
}

// Get doctor schedules
$doctor_schedules = [];
if ($action === 'schedule' && $doctor_id > 0) {
    try {
        if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctor_schedules'")) {
            $doctor_schedules = db_get_rows("
                SELECT * FROM doctor_schedules 
                WHERE doctor_id = :doctor_id 
                ORDER BY CASE 
                    WHEN day_of_week = 'Monday' THEN 1
                    WHEN day_of_week = 'Tuesday' THEN 2
                    WHEN day_of_week = 'Wednesday' THEN 3
                    WHEN day_of_week = 'Thursday' THEN 4
                    WHEN day_of_week = 'Friday' THEN 5
                    WHEN day_of_week = 'Saturday' THEN 6
                    WHEN day_of_week = 'Sunday' THEN 7
                    ELSE 8
                END, start_time", 
                [':doctor_id' => $doctor_id]
            );
        }
    } catch (PDOException $e) {
        error_log("Error fetching doctor schedules: " . $e->getMessage());
    }
}

// Page title based on action
$page_title = "Doctors";
if ($action === 'new') {
    $page_title = "Add New Doctor";
} elseif ($action === 'edit') {
    $page_title = "Edit Doctor";
} elseif ($action === 'view') {
    $page_title = "Doctor Details";
} elseif ($action === 'schedule') {
    $page_title = "Manage Doctor Schedule";
}

// Get department name for a doctor
function get_department_name($department_id, $departments) {
    foreach ($departments as $dept) {
        if ($dept['id'] == $department_id) {
            return $dept['name'];
        }
    }
    return 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AKIRA HOSPITAL</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0c4c8a;
            --secondary-color: #10aade;
            --accent-color: #21c286;
            --text-color: #333333;
            --light-bg: #f8f9fa;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-color);
        }
        
        .sidebar {
            background: linear-gradient(to bottom, #1e293b, #0c4c8a);
            color: white;
            min-height: 100vh;
            padding-top: 1rem;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.5rem 1rem;
            margin-bottom: 0.25rem;
            border-radius: 5px;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 500;
        }
        
        .logo-container {
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .hospital-logo {
            color: #10b981;
            font-weight: bold;
            font-size: 1.75rem;
            margin-bottom: 0;
        }
        
        .topbar {
            background-color: white;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 1.5rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-left: 15px;
        }
        
        .section-title::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background-color: var(--primary-color);
            border-radius: 4px;
        }
        
        .user-welcome {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .doctor-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--secondary-color);
        }
        
        .doctor-card:hover {
            transform: translateY(-5px);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #083b6f;
            border-color: #083b6f;
        }
        
        .badge-specialization {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="logo-container">
                    <h1 class="hospital-logo">AKIRA</h1>
                    <h1 class="hospital-logo">HOSPITAL</h1>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="patients.php">
                            <i class="fas fa-user-injured me-2"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="doctors.php">
                            <i class="fas fa-user-md me-2"></i> Doctors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="appointments.php">
                            <i class="fas fa-calendar-check me-2"></i> Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pharmacy.php">
                            <i class="fas fa-pills me-2"></i> Pharmacy
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="laboratory.php">
                            <i class="fas fa-file-medical-alt me-2"></i> Laboratory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="billing.php">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Billing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item mt-5">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Top Navigation Bar -->
                <div class="topbar d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0"><?php echo $page_title; ?></h4>
                    </div>
                    <div class="user-welcome">
                        Welcome, <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span> (<?php echo htmlspecialchars($admin_role); ?>)
                    </div>
                </div>
                
                <!-- Content based on action -->
                <div class="container-fluid">
                    <!-- Display alerts -->
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Different content based on action -->
                    <?php if ($action === 'list'): ?>
                        <!-- Doctors List View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">All Doctors</h5>
                                <a href="doctors.php?action=new" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> Add New Doctor
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($doctors)): ?>
                                    <div class="alert alert-info">
                                        No doctors found in the database.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#ID</th>
                                                    <th>Name</th>
                                                    <th>Specialization</th>
                                                    <th>Department</th>
                                                    <th>Phone</th>
                                                    <th>Email</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($doctors as $doctor): ?>
                                                    <tr>
                                                        <td><?php echo $doctor['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                                                        <td>
                                                            <?php if (!empty($doctor['specialization'])): ?>
                                                                <span class="badge bg-light text-dark badge-specialization">
                                                                    <?php echo htmlspecialchars($doctor['specialization']); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            echo !empty($doctor['department_id']) 
                                                                ? htmlspecialchars(get_department_name($doctor['department_id'], $departments)) 
                                                                : 'N/A'; 
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($doctor['email'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="doctors.php?action=view&id=<?php echo $doctor['id']; ?>" class="btn btn-info">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a href="doctors.php?action=edit&id=<?php echo $doctor['id']; ?>" class="btn btn-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="doctors.php?action=schedule&id=<?php echo $doctor['id']; ?>" class="btn btn-secondary">
                                                                    <i class="fas fa-calendar-alt"></i>
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $doctor['id']; ?>)" class="btn btn-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($action === 'new' || $action === 'edit'): ?>
                        <!-- Doctor Add/Edit Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo $action === 'new' ? 'Add New Doctor' : 'Edit Doctor'; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="doctors.php?action=<?php echo $action; ?><?php echo ($action === 'edit' && $doctor_id > 0) ? '&id=' . $doctor_id : ''; ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" required
                                                value="<?php echo isset($doctor['name']) ? htmlspecialchars($doctor['name']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                            <select class="form-select" id="department_id" name="department_id" required>
                                                <option value="">Select Department</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo $dept['id']; ?>" 
                                                        <?php echo (isset($doctor['department_id']) && $doctor['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($dept['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="specialization" class="form-label">Specialization <span class="text-danger">*</span></label>
                                            <select class="form-select" id="specialization" name="specialization" required>
                                                <option value="">Select Specialization</option>
                                                <option value="General Medicine">General Medicine</option>
                                                <option value="Cardiology">Cardiology</option>
                                                <option value="Neurology">Neurology</option>
                                                <option value="Orthopedics">Orthopedics</option>
                                                <option value="Pediatrics">Pediatrics</option>
                                                <option value="Dermatology">Dermatology</option>
                                                <option value="Ophthalmology">Ophthalmology</option>
                                                <option value="Psychiatry">Psychiatry</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="qualification" class="form-label">Qualification <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="qualification" name="qualification" required
                                                value="<?php echo isset($doctor['qualification']) ? htmlspecialchars($doctor['qualification']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="experience" class="form-label">Experience (Years)</label>
                                            <input type="number" class="form-control" id="experience" name="experience" min="0"
                                                value="<?php echo isset($doctor['experience']) ? htmlspecialchars($doctor['experience']) : '0'; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?php echo isset($doctor['email']) ? htmlspecialchars($doctor['email']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="phone" name="phone" required
                                                value="<?php echo isset($doctor['phone']) ? htmlspecialchars($doctor['phone']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="active" <?php echo (isset($doctor['status']) && $doctor['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo (isset($doctor['status']) && $doctor['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($doctor['address']) ? htmlspecialchars($doctor['address']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 d-flex justify-content-between">
                                        <a href="doctors.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="save_doctor" class="btn btn-primary">
                                            <?php echo $action === 'new' ? 'Save Doctor' : 'Update Doctor'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($action === 'view' && $doctor): ?>
                        <!-- Doctor Details View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Doctor Details</h5>
                                <div>
                                    <a href="doctors.php?action=schedule&id=<?php echo $doctor['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-calendar-alt me-1"></i> Schedule
                                    </a>
                                    <a href="doctors.php?action=edit&id=<?php echo $doctor['id']; ?>" class="btn btn-primary btn-sm ms-2">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </a>
                                    <a href="doctors.php" class="btn btn-secondary btn-sm ms-2">
                                        <i class="fas fa-arrow-left me-1"></i> Back to List
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-3">Personal Information</h6>
                                        <table class="table">
                                            <tr>
                                                <th width="30%">Doctor ID</th>
                                                <td><?php echo $doctor['id']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Full Name</th>
                                                <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Specialization</th>
                                                <td><?php echo htmlspecialchars($doctor['specialization'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Department</th>
                                                <td>
                                                    <?php 
                                                    echo !empty($doctor['department_id']) 
                                                        ? htmlspecialchars(get_department_name($doctor['department_id'], $departments)) 
                                                        : 'N/A'; 
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Qualification</th>
                                                <td><?php echo htmlspecialchars($doctor['qualification'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Joining Date</th>
                                                <td><?php echo htmlspecialchars($doctor['joining_date'] ?? 'N/A'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-3">Contact Information</h6>
                                        <table class="table">
                                            <tr>
                                                <th width="30%">Email</th>
                                                <td><?php echo htmlspecialchars($doctor['email'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Phone</th>
                                                <td><?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Address</th>
                                                <td><?php echo htmlspecialchars($doctor['address'] ?? 'N/A'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Additional Info Tabs -->
                                <div class="mt-4">
                                    <ul class="nav nav-tabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="schedule-tab" data-bs-toggle="tab" 
                                                data-bs-target="#schedule" type="button" role="tab" 
                                                aria-controls="schedule" aria-selected="true">
                                                Schedule
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="appointments-tab" data-bs-toggle="tab" 
                                                data-bs-target="#appointments" type="button" role="tab" 
                                                aria-controls="appointments" aria-selected="false">
                                                Appointments
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="patients-tab" data-bs-toggle="tab" 
                                                data-bs-target="#patients" type="button" role="tab" 
                                                aria-controls="patients" aria-selected="false">
                                                Patients
                                            </button>
                                        </li>
                                    </ul>
                                    <div class="tab-content p-3 border border-top-0 rounded-bottom">
                                        <div class="tab-pane fade show active" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                                            <p class="text-muted">Doctor's schedule will be displayed here.</p>
                                            <a href="schedule.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-calendar-alt me-1"></i> Manage Schedule
                                            </a>
                                        </div>
                                        <div class="tab-pane fade" id="appointments" role="tabpanel" aria-labelledby="appointments-tab">
                                            <p class="text-muted">Doctor's appointments will be displayed here.</p>
                                            <a href="appointments.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye me-1"></i> View All Appointments
                                            </a>
                                        </div>
                                        <div class="tab-pane fade" id="patients" role="tabpanel" aria-labelledby="patients-tab">
                                            <p class="text-muted">Doctor's patients will be displayed here.</p>
                                            <a href="patients.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-user-injured me-1"></i> View All Patients
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($action === 'schedule' && $doctor): ?>
                        <!-- Doctor Schedule Management -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="section-title mb-0">Schedule for <?php echo htmlspecialchars($doctor['name']); ?></h4>
                            <a href="doctors.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i> Back to Doctors
                            </a>
                        </div>
                        <div class="card shadow-sm mb-4 border-0">
                            <div class="card-header py-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.1) 0%, rgba(0, 123, 255, 0.2) 100%);">
                                <h5 class="m-0 font-weight-bold" style="color: var(--primary-color);">Manage Time Slots</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="doctors.php?action=schedule&id=<?php echo $doctor_id; ?>">
                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
                                    
                                    <div class="mb-4 d-flex justify-content-between align-items-center">
                                        <div class="doctor-info-card p-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05) 0%, rgba(0, 123, 255, 0.1) 100%); border-radius: 8px;">
                                            <h6 class="mb-2">Doctor: <span class="text-primary fw-bold"><?php echo htmlspecialchars($doctor['name']); ?></span></h6>
                                            <?php if (!empty($doctor['specialization'])): ?>
                                                <p class="mb-0">Specialization: <span class="badge" style="background: linear-gradient(135deg, #3a96dd 0%, #0078d7 100%); color: white;"><?php echo htmlspecialchars($doctor['specialization']); ?></span></p>
                                            <?php endif; ?>
                                            <?php if (!empty($doctor['department_id'])): ?>
                                                <p class="mb-0 mt-1">Department: <?php echo htmlspecialchars(get_department_name($doctor['department_id'], $departments)); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-primary btn-sm" id="addScheduleBtn" style="background: linear-gradient(135deg, #3a96dd 0%, #0078d7 100%); border: none; box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);">
                                            <i class="fas fa-plus me-1"></i> Add Time Slot
                                        </button>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="scheduleTable">
                                            <thead style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05) 0%, rgba(0, 123, 255, 0.1) 100%);">
                                                <tr>
                                                    <th style="border-bottom: 2px solid #f0f0f0;">Day</th>
                                                    <th style="border-bottom: 2px solid #f0f0f0;">Start Time</th>
                                                    <th style="border-bottom: 2px solid #f0f0f0;">End Time</th>
                                                    <th style="border-bottom: 2px solid #f0f0f0;">Max Patients</th>
                                                    <th style="border-bottom: 2px solid #f0f0f0;">Available</th>
                                                    <th style="border-bottom: 2px solid #f0f0f0;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="scheduleTableBody">
                                                <?php if (empty($doctor_schedules)): ?>
                                                    <tr class="schedule-row">
                                                        <td>
                                                            <select class="form-select" name="day[]" required>
                                                                <option value="">Select Day</option>
                                                                <option value="Monday">Monday</option>
                                                                <option value="Tuesday">Tuesday</option>
                                                                <option value="Wednesday">Wednesday</option>
                                                                <option value="Thursday">Thursday</option>
                                                                <option value="Friday">Friday</option>
                                                                <option value="Saturday">Saturday</option>
                                                                <option value="Sunday">Sunday</option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="time" class="form-control" name="start_time[]" required>
                                                        </td>
                                                        <td>
                                                            <input type="time" class="form-control" name="end_time[]" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control" name="max_patients[]" value="20" min="1" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="form-check d-flex justify-content-center">
                                                                <input class="form-check-input" type="checkbox" name="is_available[]" value="1" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-danger delete-schedule-btn">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($doctor_schedules as $schedule): ?>
                                                        <tr class="schedule-row">
                                                            <td>
                                                                <select class="form-select" name="day[]" required>
                                                                    <option value="">Select Day</option>
                                                                    <option value="Monday" <?php echo ($schedule['day_of_week'] === 'Monday') ? 'selected' : ''; ?>>Monday</option>
                                                                    <option value="Tuesday" <?php echo ($schedule['day_of_week'] === 'Tuesday') ? 'selected' : ''; ?>>Tuesday</option>
                                                                    <option value="Wednesday" <?php echo ($schedule['day_of_week'] === 'Wednesday') ? 'selected' : ''; ?>>Wednesday</option>
                                                                    <option value="Thursday" <?php echo ($schedule['day_of_week'] === 'Thursday') ? 'selected' : ''; ?>>Thursday</option>
                                                                    <option value="Friday" <?php echo ($schedule['day_of_week'] === 'Friday') ? 'selected' : ''; ?>>Friday</option>
                                                                    <option value="Saturday" <?php echo ($schedule['day_of_week'] === 'Saturday') ? 'selected' : ''; ?>>Saturday</option>
                                                                    <option value="Sunday" <?php echo ($schedule['day_of_week'] === 'Sunday') ? 'selected' : ''; ?>>Sunday</option>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="time" class="form-control" name="start_time[]" value="<?php echo $schedule['start_time']; ?>" required>
                                                            </td>
                                                            <td>
                                                                <input type="time" class="form-control" name="end_time[]" value="<?php echo $schedule['end_time']; ?>" required>
                                                            </td>
                                                            <td>
                                                                <input type="number" class="form-control" name="max_patients[]" value="<?php echo $schedule['max_patients']; ?>" min="1" required>
                                                            </td>
                                                            <td class="text-center">
                                                                <div class="form-check d-flex justify-content-center">
                                                                    <input class="form-check-input" type="checkbox" name="is_available[]" value="1" <?php echo ($schedule['is_available'] ? 'checked' : ''); ?>>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm delete-schedule-btn" style="background: linear-gradient(135deg, #ff5c8d 0%, #c50e4e 100%); color: white; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="doctors.php" class="btn btn-light" style="border: 1px solid #e0e0e0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                            <i class="fas fa-arrow-left me-1"></i> Back to Doctors
                                        </a>
                                        <button type="submit" name="save_schedule" class="btn" style="background: linear-gradient(135deg, #3a96dd 0%, #0078d7 100%); color: white; border: none; box-shadow: 0 3px 6px rgba(0,0,0,0.1);">
                                            <i class="fas fa-save me-1"></i> Save Schedule
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Schedule management is handled by the script at the bottom of the page -->
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this doctor? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(doctorId) {
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            deleteBtn.href = `doctors.php?action=delete&id=${doctorId}`;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        // Doctor Schedule Management
        document.addEventListener('DOMContentLoaded', function() {
            const addScheduleBtn = document.getElementById('addScheduleBtn');
            const scheduleTableBody = document.getElementById('scheduleTableBody');
            
            if (addScheduleBtn && scheduleTableBody) {
                // Add new schedule row
                addScheduleBtn.addEventListener('click', function() {
                    addNewScheduleRow();
                });
                
                // Initialize delete buttons for existing rows
                initializeDeleteButtons();
            }
        });
        
        function addNewScheduleRow() {
            const scheduleTableBody = document.getElementById('scheduleTableBody');
            if (!scheduleTableBody) return;
            
            const newRow = document.createElement('tr');
            newRow.className = 'schedule-row';
            newRow.innerHTML = `
                <td>
                    <select class="form-select" name="day[]" required>
                        <option value="">Select Day</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                </td>
                <td>
                    <input type="time" class="form-control" name="start_time[]" required>
                </td>
                <td>
                    <input type="time" class="form-control" name="end_time[]" required>
                </td>
                <td>
                    <input type="number" class="form-control" name="max_patients[]" min="1" value="20">
                </td>
                <td class="text-center">
                    <div class="form-check d-flex justify-content-center">
                        <input class="form-check-input" type="checkbox" name="is_available[]" value="1" checked>
                    </div>
                </td>
                <td>
                    <button type="button" class="btn btn-sm delete-schedule-btn" style="background: linear-gradient(135deg, #ff5c8d 0%, #c50e4e 100%); color: white; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            scheduleTableBody.appendChild(newRow);
            
            // Add event listener to the new delete button
            const deleteBtn = newRow.querySelector('.delete-schedule-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    if (scheduleTableBody.children.length > 1) {
                        newRow.remove();
                    } else {
                        alert('You must have at least one schedule slot.');
                    }
                });
            }
        }
        
        function initializeDeleteButtons() {
            const deleteButtons = document.querySelectorAll('.delete-schedule-btn');
            const scheduleTableBody = document.getElementById('scheduleTableBody');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (scheduleTableBody && scheduleTableBody.children.length > 1) {
                        const row = this.closest('tr');
                        if (row) row.remove();
                    } else {
                        alert('You must have at least one schedule slot.');
                    }
                });
            });
        }
    </script>
</body>
</html>