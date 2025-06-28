<?php
/**
 * AKIRA HOSPITAL Management System
 * Beds Management Page - Fixed Version
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
$bed_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Define bed types with their total counts
$bed_types = [
    'general' => ['name' => 'General Ward', 'total' => 150],
    'semi_private' => ['name' => 'Semi-Private Room', 'total' => 50],
    'private' => ['name' => 'Private Room', 'total' => 50],
    'icu' => ['name' => 'ICU', 'total' => 20],
    'nicu' => ['name' => 'NICU', 'total' => 10],
    'emergency' => ['name' => 'Emergency', 'total' => 10],
    'operation' => ['name' => 'Operation Theatre', 'total' => 10]
];

// Total beds: 300

// Define bed statuses
$bed_statuses = [
    'available' => 'Available',
    'occupied' => 'Occupied',
    'maintenance' => 'Under Maintenance',
    'reserved' => 'Reserved'
];

// Create beds table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'beds'")) {
        db_query("
            CREATE TABLE IF NOT EXISTS beds (
                id SERIAL PRIMARY KEY,
                bed_number VARCHAR(20) NOT NULL,
                ward VARCHAR(50) NOT NULL,
                type VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'available',
                patient_id INT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
            )
        ");
        error_log("Created beds table");
    }
} catch (PDOException $e) {
    error_log("Error checking/creating beds table: " . $e->getMessage());
}

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $bed_number = isset($_POST['bed_number']) ? trim($_POST['bed_number']) : '';
        $ward = isset($_POST['ward']) ? trim($_POST['ward']) : '';
        $type = isset($_POST['type']) ? trim($_POST['type']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'available';
        $patient_id = isset($_POST['patient_id']) && !empty($_POST['patient_id']) ? intval($_POST['patient_id']) : null;
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        
        // Validation
        if (empty($bed_number) || empty($ward) || empty($type)) {
            throw new Exception("Please fill all required fields");
        }
        
        // Different actions based on form submission
        if (isset($_POST['save_bed'])) {
            if ($action === 'edit' && $bed_id > 0) {
                // Update existing bed
                $sql = "UPDATE beds SET 
                        bed_number = :bed_number, 
                        ward = :ward, 
                        type = :type, 
                        status = :status, 
                        patient_id = :patient_id, 
                        notes = :notes,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id";
                
                $params = [
                    ':bed_number' => $bed_number,
                    ':ward' => $ward,
                    ':type' => $type,
                    ':status' => $status,
                    ':patient_id' => $patient_id,
                    ':notes' => $notes,
                    ':id' => $bed_id
                ];
                
                db_query($sql, $params);
                $success = "Bed updated successfully";
                
                // Redirect to bed list with updated statistics
                header("Location: beds_fixed.php");
                exit;
            } else {
                // Insert new bed
                // Using db_insert helper function for consistency
                if (db_insert('beds', [
                    'bed_number' => $bed_number,
                    'ward' => $ward,
                    'type' => $type,
                    'status' => $status,
                    'patient_id' => $patient_id,
                    'notes' => $notes
                ])) {
                    $success = "Bed added successfully";
                } else {
                    throw new Exception("Failed to add bed");
                }
                
                // Redirect to bed list with updated statistics
                header("Location: beds_fixed.php");
                exit;
            }
        } elseif (isset($_POST['delete_bed']) && $bed_id > 0) {
            // Delete bed
            db_query("DELETE FROM beds WHERE id = :id", [':id' => $bed_id]);
            $success = "Bed deleted successfully";
            
            // Redirect to bed list
            header("Location: beds_fixed.php");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get list of beds
$beds = [];
try {
    $sql = "SELECT b.*, p.name as patient_name 
            FROM beds b 
            LEFT JOIN patients p ON b.patient_id = p.id 
            ORDER BY b.ward, b.bed_number";
    $beds = db_get_rows($sql);
} catch (PDOException $e) {
    $error = "Error fetching beds: " . $e->getMessage();
}

// Get patients for dropdown
$patients = [];
try {
    $sql = "SELECT id, name FROM patients ORDER BY name";
    $patients = db_get_rows($sql);
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
}

// Count beds by status and type
$beds_by_type = [];
foreach ($bed_types as $type_key => $type_info) {
    $beds_by_type[$type_key] = [
        'total' => $type_info['total'],
        'available' => 0,
        'occupied' => 0,
        'maintenance' => 0,
        'reserved' => 0,
        'not_created' => $type_info['total'] // Default: all beds not yet created in system
    ];
}

// Count existing beds by status and type
$available_beds_count = 0;
$occupied_beds_count = 0;
$maintenance_beds_count = 0;
$reserved_beds_count = 0;
$total_beds_in_system = 0;

foreach ($beds as $bed_item) {
    $type = $bed_item['type'];
    $status = $bed_item['status'];
    $total_beds_in_system++;
    
    // Update the counts for this bed type
    if (isset($beds_by_type[$type])) {
        $beds_by_type[$type][$status]++;
        $beds_by_type[$type]['not_created']--; // Reduce count of not created beds
        
        // Update global status counts
        switch ($status) {
            case 'available':
                $available_beds_count++;
                break;
            case 'occupied':
                $occupied_beds_count++;
                break;
            case 'maintenance':
                $maintenance_beds_count++;
                break;
            case 'reserved':
                $reserved_beds_count++;
                break;
        }
    }
}

// Calculate total available beds and unregistered beds
$total_bed_capacity = 0;
foreach ($bed_types as $type_info) {
    $total_bed_capacity += $type_info['total'];
}

// Calculate beds not in system (unregistered)
$beds_not_in_system = $total_bed_capacity - $total_beds_in_system;

// Count total wards
$wards = [];
foreach ($beds as $bed_item) {
    if (!in_array($bed_item['ward'], $wards)) {
        $wards[] = $bed_item['ward'];
    }
}
$total_wards = count($wards);

// Get bed details for editing
$bed = [];
if ($action === 'edit' && $bed_id > 0) {
    try {
        $sql = "SELECT b.*, p.name as patient_name 
                FROM beds b 
                LEFT JOIN patients p ON b.patient_id = p.id 
                WHERE b.id = :id";
        $bed = db_get_row($sql, [':id' => $bed_id]);
        
        if (!$bed) {
            $error = "Bed not found";
            $action = 'list'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'list'; // Fallback to list
    }
}

// Instead of including the header, we'll use the same template as dashboard.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bed Management - AKIRA HOSPITAL</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <!-- Custom styles -->
    <style>
        :root {
            --primary-color: #10b981; /* Emerald/Teal color */
            --primary-dark: #059669;
            --secondary-color: #3b82f6; /* Blue color */
            --secondary-dark: #2563eb;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .content-area {
            padding: 1.5rem;
        }
        
        .sidebar {
            background-color: #1e293b;
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            top: 0;
            left: 0;
            padding-top: 0;
            z-index: 100;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar .nav-link {
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        
        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        
        .logo-container {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
        }
        
        .hospital-logo {
            color: var(--primary-color);
            font-size: 1.75rem;
            margin: 0;
            line-height: 1.2;
            font-weight: 700;
        }
        
        main {
            margin-left: 250px;
            padding: 1.5rem;
        }
        
        .dashboard-card {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .dashboard-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            height: 100%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:nth-child(1) .stat-card {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .stat-card:nth-child(2) .stat-card {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .stat-card:nth-child(3) .stat-card {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .stat-card:nth-child(4) .stat-card {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        /* Individual gradient backgrounds */
        .stat-card:nth-child(1) {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .stat-label {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            font-weight: 500;
            opacity: 0.8;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-subtitle {
            font-size: 0.875rem;
            opacity: 0.8;
            margin: 0;
        }
        
        .bed-status-available {
            background-color: #dcfce7;
            color: #166534;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .bed-status-occupied {
            background-color: #fee2e2;
            color: #991b1b;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .bed-status-maintenance {
            background-color: #fef3c7;
            color: #92400e;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .bed-status-reserved {
            background-color: #dbeafe;
            color: #1e40af;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .table th, .table td {
            vertical-align: middle;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.025);
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .view-all-link {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }
        
        .view-all-link i {
            margin-left: 0.25rem;
            transition: transform 0.2s;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
        }
        
        .view-all-link:hover i {
            transform: translateX(3px);
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
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #333;
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
                        <a class="nav-link" href="laboratory_fix.php">
                            <i class="fas fa-file-medical-alt me-2"></i> Laboratory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="billing_fixed.php">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Billing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="beds_fixed.php">
                            <i class="fas fa-bed me-2"></i> Beds
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="staff.php">
                            <i class="fas fa-user-nurse me-2"></i> Staff
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ambulance.php">
                            <i class="fas fa-ambulance me-2"></i> Ambulance
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
                    <li class="nav-item mt-3">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <?php if ($action === 'list'): ?>
                            Bed Management
                        <?php elseif ($action === 'new'): ?>
                            Add New Bed
                        <?php elseif ($action === 'edit'): ?>
                            Edit Bed
                        <?php endif; ?>
                    </h1>
                    <div class="user-welcome">
                        Welcome, <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($admin_role); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            
            <?php if (in_array($action, ['new', 'edit'])): ?>
                <!-- Bed Form -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?php echo $action === 'new' ? 'Add New Bed' : 'Edit Bed'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form action="beds_fixed.php?action=<?php echo $action; ?><?php echo $bed_id ? '&id=' . $bed_id : ''; ?>" method="post">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="bed_number" class="form-label">Bed Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="bed_number" name="bed_number" required
                                           value="<?php echo isset($bed['bed_number']) ? htmlspecialchars($bed['bed_number']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="ward" class="form-label">Ward <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ward" name="ward" required
                                           value="<?php echo isset($bed['ward']) ? htmlspecialchars($bed['ward']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="type" class="form-label">Bed Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="type" name="type" required>
                                        <option value="">-- Select Type --</option>
                                        <?php foreach ($bed_types as $type_key => $type_info): ?>
                                            <option value="<?php echo $type_key; ?>" <?php echo (isset($bed['type']) && $bed['type'] === $type_key) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type_info['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <?php foreach ($bed_statuses as $status_key => $status_label): ?>
                                            <option value="<?php echo $status_key; ?>" <?php echo (isset($bed['status']) && $bed['status'] === $status_key) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="patient_id" class="form-label">Assigned Patient</label>
                                    <select class="form-control" id="patient_id" name="patient_id">
                                        <option value="">-- None --</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>" <?php echo (isset($bed['patient_id']) && $bed['patient_id'] == $patient['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($patient['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($bed['notes']) ? htmlspecialchars($bed['notes']) : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" name="save_bed" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $action === 'new' ? 'Add Bed' : 'Update Bed'; ?>
                                </button>
                                <a href="beds_fixed.php" class="btn btn-secondary">Cancel</a>
                                
                                <?php if ($action === 'edit'): ?>
                                    <button type="submit" name="delete_bed" class="btn btn-danger float-end" onclick="return confirm('Are you sure you want to delete this bed?');">
                                        <i class="fas fa-trash"></i> Delete Bed
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Bed Summary -->
                <div class="row mb-4">
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon stat-beds-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                            <p class="stat-label">TOTAL BEDS</p>
                            <p class="stat-number"><?php echo $total_bed_capacity; ?></p>
                            <p class="stat-subtitle"><i class="fas fa-hospital"></i> Hospital Capacity</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon stat-patients-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <p class="stat-label">AVAILABLE BEDS</p>
                            <p class="stat-number"><?php echo $available_beds_count + $beds_not_in_system; ?></p>
                            <p class="stat-subtitle">
                                <i class="fas fa-info-circle"></i> 
                                System: <?php echo $available_beds_count; ?>, Unregistered: <?php echo $beds_not_in_system; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon stat-appointments-icon">
                                <i class="fas fa-procedures"></i>
                            </div>
                            <p class="stat-label">OCCUPIED BEDS</p>
                            <p class="stat-number"><?php echo $occupied_beds_count; ?></p>
                            <p class="stat-subtitle"><i class="fas fa-user-injured"></i> Currently In Use</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon stat-staff-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <p class="stat-label">UNAVAILABLE</p>
                            <p class="stat-number"><?php echo ($maintenance_beds_count + $reserved_beds_count); ?></p>
                            <p class="stat-subtitle">
                                <i class="fas fa-wrench"></i> Maintenance: <?php echo $maintenance_beds_count; ?>, 
                                <i class="fas fa-bookmark"></i> Reserved: <?php echo $reserved_beds_count; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Beds by Type Summary -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Beds by Type</h6>
                        <a href="beds_fixed.php?action=new" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add New Bed
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="bedsTypeTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Bed Type</th>
                                        <th>Total Capacity</th>
                                        <th>Registered in System</th>
                                        <th>Available</th>
                                        <th>Occupied</th>
                                        <th>Under Maintenance</th>
                                        <th>Reserved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bed_types as $type_key => $type_info): ?>
                                        <?php 
                                        $registered = isset($beds_by_type[$type_key]) ? 
                                            $type_info['total'] - $beds_by_type[$type_key]['not_created'] : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($type_info['name']); ?></td>
                                            <td><?php echo $type_info['total']; ?></td>
                                            <td><?php echo $registered; ?></td>
                                            <td>
                                                <?php
                                                $available = isset($beds_by_type[$type_key]) ? 
                                                    $beds_by_type[$type_key]['available'] + $beds_by_type[$type_key]['not_created'] : 0;
                                                echo $available;
                                                ?>
                                                <span class="text-muted">
                                                    (<?php echo isset($beds_by_type[$type_key]) ? $beds_by_type[$type_key]['available'] : 0; ?> + 
                                                    <?php echo isset($beds_by_type[$type_key]) ? $beds_by_type[$type_key]['not_created'] : 0; ?>)
                                                </span>
                                            </td>
                                            <td><?php echo isset($beds_by_type[$type_key]) ? $beds_by_type[$type_key]['occupied'] : 0; ?></td>
                                            <td><?php echo isset($beds_by_type[$type_key]) ? $beds_by_type[$type_key]['maintenance'] : 0; ?></td>
                                            <td><?php echo isset($beds_by_type[$type_key]) ? $beds_by_type[$type_key]['reserved'] : 0; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Beds List -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Registered Beds</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="bedsDataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Bed Number</th>
                                        <th>Ward</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Patient</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($beds) > 0): ?>
                                        <?php foreach ($beds as $bed_item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($bed_item['bed_number']); ?></td>
                                                <td><?php echo htmlspecialchars($bed_item['ward']); ?></td>
                                                <td>
                                                    <?php echo isset($bed_types[$bed_item['type']]) ? 
                                                        htmlspecialchars($bed_types[$bed_item['type']]['name']) : 
                                                        htmlspecialchars($bed_item['type']); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_class = '';
                                                    switch ($bed_item['status']) {
                                                        case 'available':
                                                            $status_class = 'bed-status-available';
                                                            break;
                                                        case 'occupied':
                                                            $status_class = 'bed-status-occupied';
                                                            break;
                                                        case 'maintenance':
                                                            $status_class = 'bed-status-maintenance';
                                                            break;
                                                        case 'reserved':
                                                            $status_class = 'bed-status-reserved';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="<?php echo $status_class; ?>">
                                                        <?php echo isset($bed_statuses[$bed_item['status']]) ? 
                                                            htmlspecialchars($bed_statuses[$bed_item['status']]) : 
                                                            htmlspecialchars(ucfirst($bed_item['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo !empty($bed_item['patient_name']) ? 
                                                        htmlspecialchars($bed_item['patient_name']) : 
                                                        'N/A'; ?>
                                                </td>
                                                <td>
                                                    <a href="beds_fixed.php?action=edit&id=<?php echo $bed_item['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No beds found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#bedsDataTable').DataTable();
            
            // Update patient field based on status
            $('#status').change(function() {
                if ($(this).val() === 'occupied') {
                    $('#patient_id').prop('required', true);
                    $('label[for="patient_id"]').append('<span class="text-danger">*</span>');
                } else {
                    $('#patient_id').prop('required', false);
                    $('label[for="patient_id"] .text-danger').remove();
                    
                    // If status is not occupied, clear patient selection
                    if ($(this).val() !== 'occupied') {
                        $('#patient_id').val('');
                    }
                }
            });
            
            // Trigger on load
            $('#status').trigger('change');
        });
    </script>
</body>
</html>