<?php
/**
 * AKIRA HOSPITAL Management System
 * Patients Management Page
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

// Get admin information
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// Check if action is specified (new, edit, view, delete)
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Create patients table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'patients'")) {
        db_query("
            CREATE TABLE IF NOT EXISTS patients (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NULL,
                phone VARCHAR(20) NULL,
                address TEXT NULL,
                gender VARCHAR(10) NULL,
                date_of_birth DATE NULL,
                blood_group VARCHAR(5) NULL,
                medical_history TEXT NULL,
                allergies TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        error_log("Created patients table");
    }
} catch (PDOException $e) {
    error_log("Error creating patients table: " . $e->getMessage());
}

// Get list of patients
$patients = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'patients'")) {
        $patients = db_get_rows("SELECT * FROM patients ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
}

// Handle form submission for new/edit patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_patient'])) {
    try {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $blood_group = $_POST['blood_group'] ?? '';
        
        // Validation
        if (empty($name)) {
            $error = "Patient name is required";
        } else {
            if ($action === 'new') {
                // Insert new patient
                $sql = "INSERT INTO patients (name, email, phone, address, gender, date_of_birth, blood_group) 
                        VALUES (:name, :email, :phone, :address, :gender, :date_of_birth, :blood_group)";
                $params = [
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':gender' => $gender,
                    ':date_of_birth' => $date_of_birth,
                    ':blood_group' => $blood_group
                ];
                db_query($sql, $params);
                $success = "Patient added successfully";
                // Redirect to patient list
                header("Location: patients.php");
                exit;
            } elseif ($action === 'edit' && $patient_id > 0) {
                // Update existing patient
                $sql = "UPDATE patients SET name = :name, email = :email, phone = :phone, 
                        address = :address, gender = :gender, date_of_birth = :date_of_birth, 
                        blood_group = :blood_group WHERE id = :id";
                $params = [
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':gender' => $gender,
                    ':date_of_birth' => $date_of_birth,
                    ':blood_group' => $blood_group,
                    ':id' => $patient_id
                ];
                db_query($sql, $params);
                $success = "Patient updated successfully";
                // Redirect to patient list
                header("Location: patients.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get patient data for edit/view
$patient = null;
if (($action === 'edit' || $action === 'view') && $patient_id > 0) {
    try {
        $patient = db_get_row("SELECT * FROM patients WHERE id = :id", [':id' => $patient_id]);
        if (!$patient) {
            $error = "Patient not found";
            $action = 'list'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'list'; // Fallback to list
    }
}

// Handle delete action
if ($action === 'delete' && $patient_id > 0) {
    try {
        db_query("DELETE FROM patients WHERE id = :id", [':id' => $patient_id]);
        $success = "Patient deleted successfully";
        // Redirect to patient list
        header("Location: patients.php");
        exit;
    } catch (PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Page title based on action
$page_title = "Patients";
if ($action === 'new') {
    $page_title = "Add New Patient";
} elseif ($action === 'edit') {
    $page_title = "Edit Patient";
} elseif ($action === 'view') {
    $page_title = "Patient Details";
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
        
        .patient-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--primary-color);
        }
        
        .patient-card:hover {
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
                        <a class="nav-link active" href="patients.php">
                            <i class="fas fa-user-injured me-2"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="doctors.php">
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
                        <!-- Patients List View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">All Patients</h5>
                                <a href="patients.php?action=new" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> Add New Patient
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($patients)): ?>
                                    <div class="alert alert-info">
                                        No patients found in the database.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#ID</th>
                                                    <th>Name</th>
                                                    <th>Gender</th>
                                                    <th>Blood Group</th>
                                                    <th>Phone</th>
                                                    <th>Email</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($patients as $patient): ?>
                                                    <tr>
                                                        <td><?php echo $patient['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($patient['blood_group'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="patients.php?action=view&id=<?php echo $patient['id']; ?>" class="btn btn-info">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $patient['id']; ?>)" class="btn btn-danger">
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
                        <!-- Patient Add/Edit Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo $action === 'new' ? 'Add New Patient' : 'Edit Patient'; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" required
                                                value="<?php echo isset($patient['name']) ? htmlspecialchars($patient['name']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?php echo isset($patient['email']) ? htmlspecialchars($patient['email']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="text" class="form-control" id="phone" name="phone"
                                                value="<?php echo isset($patient['phone']) ? htmlspecialchars($patient['phone']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                                value="<?php echo isset($patient['date_of_birth']) ? htmlspecialchars($patient['date_of_birth']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="gender" class="form-label">Gender</label>
                                            <select class="form-select" id="gender" name="gender">
                                                <option value="">Select Gender</option>
                                                <option value="Male" <?php echo (isset($patient['gender']) && $patient['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo (isset($patient['gender']) && $patient['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                                <option value="Other" <?php echo (isset($patient['gender']) && $patient['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="blood_group" class="form-label">Blood Group</label>
                                            <select class="form-select" id="blood_group" name="blood_group">
                                                <option value="">Select Blood Group</option>
                                                <option value="A+" <?php echo (isset($patient['blood_group']) && $patient['blood_group'] === 'A+') ? 'selected' : ''; ?>>A+</option>
                                                <option value="A-" <?php echo (isset($patient['blood_group']) && $patient['blood_group'] === 'A-') ? 'selected' : ''; ?>>A-</option>
                                                <option value="B+" <?php echo (isset($patient['blood_group']) && $patient['blood_group'] === 'B+') ? 'selected' : ''; ?>>B+</option>
                                                <option value="B-" <?php echo (isset($patient['blood_group']) && $patient['blood_group'] === 'B-') ? 'selected' : ''; ?>>B-</option>
                                                <option value="AB+" <?php echo (isset($patient['blood_group']) && $patient['blood_group'] === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                                <option value="AB-" <?php echo (isset($patient['blood_group']) && $patient['blood_group'] === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                                <option value="O+" <?php echo (isset($patient['blood_group']) && $patient['blood_group'] === 'O+') ? 'selected' : ''; ?>>O+</option>
                                                <option value="O-" <?php echo (isset($patient['blood_group']) && $patient['blood_group'] === 'O-') ? 'selected' : ''; ?>>O-</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($patient['address']) ? htmlspecialchars($patient['address']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 d-flex justify-content-between">
                                        <a href="patients.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="save_patient" class="btn btn-primary">
                                            <?php echo $action === 'new' ? 'Save Patient' : 'Update Patient'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($action === 'view' && $patient): ?>
                        <!-- Patient Details View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Patient Details</h5>
                                <div>
                                    <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </a>
                                    <a href="patients.php" class="btn btn-secondary btn-sm ms-2">
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
                                                <th width="30%">Patient ID</th>
                                                <td><?php echo $patient['id']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Full Name</th>
                                                <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Gender</th>
                                                <td><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date of Birth</th>
                                                <td><?php echo htmlspecialchars($patient['date_of_birth'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Blood Group</th>
                                                <td><?php echo htmlspecialchars($patient['blood_group'] ?? 'N/A'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-3">Contact Information</h6>
                                        <table class="table">
                                            <tr>
                                                <th width="30%">Email</th>
                                                <td><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Phone</th>
                                                <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Address</th>
                                                <td><?php echo htmlspecialchars($patient['address'] ?? 'N/A'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Additional Info Tabs -->
                                <div class="mt-4">
                                    <ul class="nav nav-tabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="appointments-tab" data-bs-toggle="tab" 
                                                data-bs-target="#appointments" type="button" role="tab" 
                                                aria-controls="appointments" aria-selected="true">
                                                Appointments
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="prescriptions-tab" data-bs-toggle="tab" 
                                                data-bs-target="#prescriptions" type="button" role="tab" 
                                                aria-controls="prescriptions" aria-selected="false">
                                                Prescriptions
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="invoices-tab" data-bs-toggle="tab" 
                                                data-bs-target="#invoices" type="button" role="tab" 
                                                aria-controls="invoices" aria-selected="false">
                                                Invoices
                                            </button>
                                        </li>
                                    </ul>
                                    <div class="tab-content p-3 border border-top-0 rounded-bottom">
                                        <div class="tab-pane fade show active" id="appointments" role="tabpanel" aria-labelledby="appointments-tab">
                                            <p class="text-muted">Recent appointments will be displayed here.</p>
                                            <a href="appointments.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye me-1"></i> View All Appointments
                                            </a>
                                        </div>
                                        <div class="tab-pane fade" id="prescriptions" role="tabpanel" aria-labelledby="prescriptions-tab">
                                            <p class="text-muted">Patient's prescriptions will be displayed here.</p>
                                            <a href="prescriptions.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye me-1"></i> View All Prescriptions
                                            </a>
                                        </div>
                                        <div class="tab-pane fade" id="invoices" role="tabpanel" aria-labelledby="invoices-tab">
                                            <p class="text-muted">Patient's invoices will be displayed here.</p>
                                            <a href="billing.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye me-1"></i> View All Invoices
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                    Are you sure you want to delete this patient? This action cannot be undone.
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
        function confirmDelete(patientId) {
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            deleteBtn.href = `patients.php?action=delete&id=${patientId}`;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>