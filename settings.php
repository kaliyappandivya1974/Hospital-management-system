<?php
/**
 * AKIRA HOSPITAL Management System
 * Settings Management Page
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

// Define settings sections
$section = isset($_GET['section']) ? $_GET['section'] : 'general';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // General Settings
        if (isset($_POST['save_general'])) {
            $hospital_name = $_POST['hospital_name'] ?? '';
            $hospital_address = $_POST['hospital_address'] ?? '';
            $hospital_contact = $_POST['hospital_contact'] ?? '';
            $hospital_email = $_POST['hospital_email'] ?? '';
            
            // Check if settings table exists
            if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'settings'")) {
                db_query("
                    CREATE TABLE IF NOT EXISTS settings (
                        id SERIAL PRIMARY KEY,
                        setting_key VARCHAR(50) NOT NULL UNIQUE,
                        setting_value TEXT NULL,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            }
            
            // Update or insert settings
            $settings = [
                'hospital_name' => $hospital_name,
                'hospital_address' => $hospital_address,
                'hospital_contact' => $hospital_contact,
                'hospital_email' => $hospital_email
            ];
            
            foreach ($settings as $key => $value) {
                $existing = db_get_row("SELECT id FROM settings WHERE setting_key = :key", [':key' => $key]);
                if ($existing) {
                    db_query("UPDATE settings SET setting_value = :value, updated_at = CURRENT_TIMESTAMP WHERE setting_key = :key", 
                            [':value' => $value, ':key' => $key]);
                } else {
                    db_query("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)", 
                            [':key' => $key, ':value' => $value]);
                }
            }
            
            $success = "General settings updated successfully";
        }
        
        // User Settings
        else if (isset($_POST['save_user'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate passwords
            if (empty($current_password)) {
                $error = "Current password is required";
            } else if (empty($new_password)) {
                $error = "New password is required";
            } else if ($new_password !== $confirm_password) {
                $error = "New passwords do not match";
            } else {
                // Verify current password
                $user = db_get_row("SELECT id, password FROM users WHERE id = :id", [':id' => $admin_id]);
                if (!$user) {
                    $error = "User not found";
                } else if (!password_verify($current_password, $user['password'])) {
                    $error = "Current password is incorrect";
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    db_query("UPDATE users SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :id", 
                            [':password' => $hashed_password, ':id' => $admin_id]);
                    $success = "Password updated successfully";
                }
            }
        }
        
        // System Settings
        else if (isset($_POST['save_system'])) {
            $date_format = $_POST['date_format'] ?? 'Y-m-d';
            $timezone = $_POST['timezone'] ?? 'UTC';
            $currency_symbol = $_POST['currency_symbol'] ?? '₹';
            $language = $_POST['language'] ?? 'en';
            
            // Check if settings table exists
            if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'settings'")) {
                db_query("
                    CREATE TABLE IF NOT EXISTS settings (
                        id SERIAL PRIMARY KEY,
                        setting_key VARCHAR(50) NOT NULL UNIQUE,
                        setting_value TEXT NULL,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            }
            
            // Update or insert settings
            $settings = [
                'date_format' => $date_format,
                'timezone' => $timezone,
                'currency_symbol' => $currency_symbol,
                'language' => $language
            ];
            
            foreach ($settings as $key => $value) {
                $existing = db_get_row("SELECT id FROM settings WHERE setting_key = :key", [':key' => $key]);
                if ($existing) {
                    db_query("UPDATE settings SET setting_value = :value, updated_at = CURRENT_TIMESTAMP WHERE setting_key = :key", 
                            [':value' => $value, ':key' => $key]);
                } else {
                    db_query("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)", 
                            [':key' => $key, ':value' => $value]);
                }
            }
            
            $success = "System settings updated successfully";
        }
        
        // Department Settings
        else if (isset($_POST['save_department'])) {
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $department_id = intval($_POST['department_id'] ?? 0);
            
            // Validate department name
            if (empty($name)) {
                $error = "Department name is required";
            } else {
                // Check if departments table exists
                if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'departments'")) {
                    db_query("
                        CREATE TABLE IF NOT EXISTS departments (
                            id SERIAL PRIMARY KEY,
                            name VARCHAR(100) NOT NULL UNIQUE,
                            description TEXT NULL,
                            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                }
                
                if ($department_id > 0) {
                    // Update existing department
                    db_query("UPDATE departments SET name = :name, description = :description, updated_at = CURRENT_TIMESTAMP WHERE id = :id", 
                            [':name' => $name, ':description' => $description, ':id' => $department_id]);
                    $success = "Department updated successfully";
                } else {
                    // Insert new department
                    db_query("INSERT INTO departments (name, description) VALUES (:name, :description)", 
                            [':name' => $name, ':description' => $description]);
                    $success = "Department added successfully";
                }
            }
        }
        
        // Delete Department
        else if (isset($_POST['delete_department'])) {
            $department_id = intval($_POST['department_id'] ?? 0);
            
            if ($department_id > 0) {
                // Check if department is in use
                $doctors_count = db_get_row("SELECT COUNT(*) AS count FROM doctors WHERE department_id = :id", [':id' => $department_id]);
                
                if ($doctors_count && $doctors_count['count'] > 0) {
                    $error = "Cannot delete department: it has associated doctors";
                } else {
                    // Delete department
                    db_query("DELETE FROM departments WHERE id = :id", [':id' => $department_id]);
                    $success = "Department deleted successfully";
                }
            }
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get current settings
$settings = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'settings'")) {
        $setting_rows = db_get_rows("SELECT setting_key, setting_value FROM settings");
        foreach ($setting_rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Get departments
$departments = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'departments'")) {
        $departments = db_get_rows("SELECT * FROM departments ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Default settings values
$hospital_name = $settings['hospital_name'] ?? 'AKIRA HOSPITAL';
$hospital_address = $settings['hospital_address'] ?? '123 Health Street, Medical District, City - 123456';
$hospital_contact = $settings['hospital_contact'] ?? '+91 123-456-7890';
$hospital_email = $settings['hospital_email'] ?? 'info@akirahospital.com';

$date_format = $settings['date_format'] ?? 'Y-m-d';
$timezone = $settings['timezone'] ?? 'UTC';
$currency_symbol = $settings['currency_symbol'] ?? '₹';
$language = $settings['language'] ?? 'en';

// Get department for edit
$edit_department = null;
if ($section === 'departments' && isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $department_id = intval($_GET['edit']);
    try {
        $edit_department = db_get_row("SELECT * FROM departments WHERE id = :id", [':id' => $department_id]);
    } catch (PDOException $e) {
        error_log("Error fetching department: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - AKIRA HOSPITAL</title>
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
        
        .settings-menu .nav-link {
            color: var(--text-color);
            border-radius: 0;
            padding: 0.75rem 1rem;
            border-left: 3px solid transparent;
        }
        
        .settings-menu .nav-link.active {
            background-color: rgba(16, 174, 222, 0.1);
            border-left: 3px solid var(--secondary-color);
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .settings-menu .nav-link:hover:not(.active) {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .settings-menu .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #083b6f;
            border-color: #083b6f;
        }
        
        .department-item {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 3px solid var(--secondary-color);
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
                            <i class="fas fa-flask me-2"></i> Laboratory
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
                        <a class="nav-link active" href="settings.php">
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
                        <h4 class="mb-0">Settings</h4>
                    </div>
                    <div class="user-welcome">
                        Welcome, <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span> (<?php echo htmlspecialchars($admin_role); ?>)
                    </div>
                </div>
                
                <!-- Settings Content -->
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
                    
                    <div class="row">
                        <!-- Settings Menu -->
                        <div class="col-md-3">
                            <div class="card shadow-sm">
                                <div class="card-body p-0">
                                    <div class="list-group settings-menu">
                                        <a href="settings.php?section=general" class="list-group-item list-group-item-action nav-link <?php echo $section === 'general' ? 'active' : ''; ?>">
                                            <i class="fas fa-hospital"></i> General Settings
                                        </a>
                                        <a href="settings.php?section=user" class="list-group-item list-group-item-action nav-link <?php echo $section === 'user' ? 'active' : ''; ?>">
                                            <i class="fas fa-user-cog"></i> User Settings
                                        </a>
                                        <a href="settings.php?section=system" class="list-group-item list-group-item-action nav-link <?php echo $section === 'system' ? 'active' : ''; ?>">
                                            <i class="fas fa-sliders-h"></i> System Settings
                                        </a>
                                        <a href="settings.php?section=departments" class="list-group-item list-group-item-action nav-link <?php echo $section === 'departments' ? 'active' : ''; ?>">
                                            <i class="fas fa-building"></i> Departments
                                        </a>
                                        <a href="settings.php?section=database" class="list-group-item list-group-item-action nav-link <?php echo $section === 'database' ? 'active' : ''; ?>">
                                            <i class="fas fa-database"></i> Database Management
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Settings Content -->
                        <div class="col-md-9">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <?php if ($section === 'general'): ?>
                                        <!-- General Settings -->
                                        <h5 class="section-title">General Settings</h5>
                                        <form method="POST" action="settings.php?section=general">
                                            <div class="mb-3">
                                                <label for="hospital_name" class="form-label">Hospital Name</label>
                                                <input type="text" class="form-control" id="hospital_name" name="hospital_name" value="<?php echo htmlspecialchars($hospital_name); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="hospital_address" class="form-label">Hospital Address</label>
                                                <textarea class="form-control" id="hospital_address" name="hospital_address" rows="3"><?php echo htmlspecialchars($hospital_address); ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="hospital_contact" class="form-label">Contact Number</label>
                                                        <input type="text" class="form-control" id="hospital_contact" name="hospital_contact" value="<?php echo htmlspecialchars($hospital_contact); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="hospital_email" class="form-label">Email Address</label>
                                                        <input type="email" class="form-control" id="hospital_email" name="hospital_email" value="<?php echo htmlspecialchars($hospital_email); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button type="submit" name="save_general" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif ($section === 'user'): ?>
                                        <!-- User Settings -->
                                        <h5 class="section-title">User Settings</h5>
                                        <form method="POST" action="settings.php?section=user">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button type="submit" name="save_user" class="btn btn-primary">Change Password</button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif ($section === 'system'): ?>
                                        <!-- System Settings -->
                                        <h5 class="section-title">System Settings</h5>
                                        <form method="POST" action="settings.php?section=system">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="date_format" class="form-label">Date Format</label>
                                                        <select class="form-select" id="date_format" name="date_format">
                                                            <option value="Y-m-d" <?php echo $date_format === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2023-12-31)</option>
                                                            <option value="d-m-Y" <?php echo $date_format === 'd-m-Y' ? 'selected' : ''; ?>>DD-MM-YYYY (31-12-2023)</option>
                                                            <option value="m/d/Y" <?php echo $date_format === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (12/31/2023)</option>
                                                            <option value="d/m/Y" <?php echo $date_format === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (31/12/2023)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="timezone" class="form-label">Timezone</label>
                                                        <select class="form-select" id="timezone" name="timezone">
                                                            <option value="UTC" <?php echo $timezone === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                                            <option value="Asia/Kolkata" <?php echo $timezone === 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata (IST)</option>
                                                            <option value="America/New_York" <?php echo $timezone === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (EST)</option>
                                                            <option value="Europe/London" <?php echo $timezone === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                                        <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?php echo htmlspecialchars($currency_symbol); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="language" class="form-label">Language</label>
                                                        <select class="form-select" id="language" name="language">
                                                            <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                                                            <option value="hi" <?php echo $language === 'hi' ? 'selected' : ''; ?>>Hindi</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button type="submit" name="save_system" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif ($section === 'departments'): ?>
                                        <!-- Departments Management -->
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <h5 class="section-title mb-0">Departments</h5>
                                            <?php if (!$edit_department): ?>
                                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                                                    <i class="fas fa-plus me-1"></i> Add Department
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($edit_department): ?>
                                            <!-- Edit Department Form -->
                                            <div class="card bg-light mb-4">
                                                <div class="card-body">
                                                    <h6 class="card-title">Edit Department</h6>
                                                    <form method="POST" action="settings.php?section=departments">
                                                        <input type="hidden" name="department_id" value="<?php echo $edit_department['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label for="name" class="form-label">Department Name</label>
                                                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($edit_department['name']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="description" class="form-label">Description</label>
                                                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($edit_department['description'] ?? ''); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="d-flex justify-content-between">
                                                            <a href="settings.php?section=departments" class="btn btn-secondary">Cancel</a>
                                                            <button type="submit" name="save_department" class="btn btn-primary">Save Department</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Departments List -->
                                        <?php if (empty($departments)): ?>
                                            <div class="alert alert-info">
                                                No departments found. Click "Add Department" to create one.
                                            </div>
                                        <?php else: ?>
                                            <div class="row">
                                                <?php 
                                                // Create a copy of departments array to avoid duplicates
                                                $unique_departments = [];
                                                $seen_ids = [];
                                                
                                                foreach ($departments as $dept) {
                                                    if (!in_array($dept['id'], $seen_ids)) {
                                                        $seen_ids[] = $dept['id'];
                                                        $unique_departments[] = $dept;
                                                    }
                                                }
                                                
                                                foreach ($unique_departments as $dept): 
                                                ?>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="department-item">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($dept['name']); ?></h6>
                                                                <div>
                                                                    <a href="settings.php?section=departments&edit=<?php echo $dept['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                            data-bs-toggle="modal" 
                                                                            data-bs-target="#deleteDepartmentModal" 
                                                                            data-department-id="<?php echo $dept['id']; ?>"
                                                                            data-department-name="<?php echo htmlspecialchars($dept['name']); ?>"
                                                                            title="Delete">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty($dept['description'])): ?>
                                                                <p class="mt-2 mb-0 small text-muted"><?php echo htmlspecialchars($dept['description']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Add Department Modal -->
                                        <div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Add New Department</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="POST" action="settings.php?section=departments">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="modal_name" class="form-label">Department Name</label>
                                                                <input type="text" class="form-control" id="modal_name" name="name" required>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label for="modal_description" class="form-label">Description</label>
                                                                <textarea class="form-control" id="modal_description" name="description" rows="3"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="save_department" class="btn btn-primary">Save Department</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Delete Department Modal -->
                                        <div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="POST" action="settings.php?section=departments">
                                                        <div class="modal-body">
                                                            <input type="hidden" id="delete_department_id" name="department_id" value="">
                                                            <p>Are you sure you want to delete the department: <strong id="delete_department_name"></strong>?</p>
                                                            <p class="text-danger mb-0">This action cannot be undone.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="delete_department" class="btn btn-danger">Delete Department</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                    <?php elseif ($section === 'database'): ?>
                                        <!-- Database Management -->
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <h5 class="section-title mb-0">Database Management</h5>
                                        </div>
                                        
                                        <!-- Database Info Card -->
                                        <div class="card shadow-sm mb-4">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Database Information</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <ul class="list-group list-group-flush">
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                <span><strong>Database Type:</strong></span>
                                                                <span class="badge bg-info">PostgreSQL</span>
                                                            </li>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                <span><strong>Mode:</strong></span>
                                                                <span class="badge bg-warning">XAMPP Compatibility</span>
                                                            </li>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                <span><strong>Status:</strong></span>
                                                                <span class="badge bg-success">Connected</span>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <ul class="list-group list-group-flush">
                                                            <?php
                                                                // Get table counts
                                                                $tables_info = [];
                                                                try {
                                                                    $tables = db_get_rows("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
                                                                    $total_tables = count($tables);
                                                                    
                                                                    // Get row counts for major tables
                                                                    $patients_count = 0;
                                                                    $doctors_count = 0;
                                                                    $appointments_count = 0;
                                                                    
                                                                    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'patients'")) {
                                                                        $patients_count = db_get_row("SELECT COUNT(*) as count FROM patients");
                                                                        $patients_count = $patients_count ? $patients_count['count'] : 0;
                                                                    }
                                                                    
                                                                    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctors'")) {
                                                                        $doctors_count = db_get_row("SELECT COUNT(*) as count FROM doctors");
                                                                        $doctors_count = $doctors_count ? $doctors_count['count'] : 0;
                                                                    }
                                                                    
                                                                    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'appointments'")) {
                                                                        $appointments_count = db_get_row("SELECT COUNT(*) as count FROM appointments");
                                                                        $appointments_count = $appointments_count ? $appointments_count['count'] : 0;
                                                                    }
                                                                } catch (PDOException $e) {
                                                                    error_log("Error fetching database tables: " . $e->getMessage());
                                                                    $total_tables = 0;
                                                                }
                                                            ?>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                <span><strong>Total Tables:</strong></span>
                                                                <span class="badge bg-primary"><?php echo $total_tables; ?></span>
                                                            </li>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                <span><strong>Patients:</strong></span>
                                                                <span class="badge bg-secondary"><?php echo $patients_count; ?> records</span>
                                                            </li>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                <span><strong>Doctors:</strong></span>
                                                                <span class="badge bg-secondary"><?php echo $doctors_count; ?> records</span>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Backup and Restore -->
                                        <div class="card shadow-sm mb-4">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0"><i class="fas fa-save me-2"></i>Backup & Restore</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <div class="backup-section p-3 border rounded">
                                                            <h6><i class="fas fa-download text-primary me-2"></i>Backup Database</h6>
                                                            <p class="text-muted small">Create a backup of your current database. This includes all data like patients, doctors, appointments, etc.</p>
                                                            <form method="POST" action="db_backup.php">
                                                                <div class="d-grid">
                                                                    <button type="submit" class="btn btn-outline-primary" name="create_backup">
                                                                        <i class="fas fa-download me-2"></i>Create Backup
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="restore-section p-3 border rounded">
                                                            <h6><i class="fas fa-upload text-warning me-2"></i>Restore Database</h6>
                                                            <p class="text-muted small">Restore a previously created backup. Warning: This will overwrite your current data.</p>
                                                            <form method="POST" action="db_restore.php" enctype="multipart/form-data">
                                                                <div class="mb-3">
                                                                    <input type="file" class="form-control form-control-sm" name="backup_file" accept=".sql">
                                                                </div>
                                                                <div class="d-grid">
                                                                    <button type="submit" class="btn btn-outline-warning" name="restore_backup">
                                                                        <i class="fas fa-upload me-2"></i>Restore Backup
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Database Maintenance -->
                                        <div class="card shadow-sm mb-4">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Database Maintenance</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="maintenance-option p-3 border rounded mb-3">
                                                            <h6><i class="fas fa-broom text-info me-2"></i>Clean Old Records</h6>
                                                            <p class="text-muted small">Remove old appointment records that are no longer needed.</p>
                                                            <form method="POST" action="db_maintenance.php">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Records older than</label>
                                                                    <select class="form-select form-select-sm" name="clean_period">
                                                                        <option value="6">6 months</option>
                                                                        <option value="12" selected>1 year</option>
                                                                        <option value="24">2 years</option>
                                                                        <option value="36">3 years</option>
                                                                    </select>
                                                                </div>
                                                                <div class="d-grid">
                                                                    <button type="submit" class="btn btn-sm btn-outline-info" name="clean_old_records">
                                                                        Run Cleanup
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="maintenance-option p-3 border rounded mb-3">
                                                            <h6><i class="fas fa-wrench text-secondary me-2"></i>Optimize Database</h6>
                                                            <p class="text-muted small">Run optimization routines on database tables.</p>
                                                            <form method="POST" action="db_maintenance.php">
                                                                <div class="d-grid">
                                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" name="optimize_db">
                                                                        Optimize Now
                                                                    </button>
                                                                </div>
                                                            </form>
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
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete department modal data
        document.addEventListener('DOMContentLoaded', function() {
            const deleteDepartmentModal = document.getElementById('deleteDepartmentModal');
            if (deleteDepartmentModal) {
                deleteDepartmentModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const departmentId = button.getAttribute('data-department-id');
                    const departmentName = button.getAttribute('data-department-name');
                    
                    document.getElementById('delete_department_id').value = departmentId;
                    document.getElementById('delete_department_name').textContent = departmentName;
                });
            }
        });
    </script>
</body>
</html>