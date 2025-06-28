<?php
/**
 * AKIRA HOSPITAL Management System
 * Laboratory Management Module
 */

// Include authentication check and database connection
require_once 'includes/auth_check.php';
require_once 'db_connect.php';

// Initialize variables
$action = $_GET['action'] ?? 'dashboard';
$error = '';
$success = '';

// Handle AJAX requests
if ($action === 'get_order') {
    try {
        $order_id = (int)$_GET['id'];
        if ($order_id <= 0) {
            throw new Exception("Invalid order ID");
        }
        
        // Get order details
        $stmt = $pdo->prepare("
            SELECT plt.*, p.name as patient_name, lt.name as test_name,
                   d.name as doctor_name, DATE_FORMAT(plt.test_date, '%Y-%m-%d') as formatted_date
            FROM patient_lab_tests plt
            JOIN patients p ON plt.patient_id = p.id
            JOIN lab_tests lt ON plt.lab_test_id = lt.id
            LEFT JOIN doctors d ON plt.requested_by = d.id
            WHERE plt.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        // Format response
        $response = [
            'id' => $order['id'],
            'patient_name' => $order['patient_name'],
            'test_name' => $order['test_name'],
            'doctor_name' => $order['doctor_name'] ? 'Dr. ' . $order['doctor_name'] : 'N/A',
            'test_date' => $order['formatted_date'],
            'results' => $order['results'],
            'status' => $order['status']
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Handle results update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_results') {
    try {
        $order_id = (int)$_POST['order_id'];
        $results = trim($_POST['results']);
        $status = $_POST['status'];
        
        if ($order_id <= 0 || empty($results)) {
            throw new Exception("Results cannot be empty");
        }
        
        // Validate status
        $valid_statuses = ['requested', 'in_progress', 'completed'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception("Invalid status");
        }
        
        // Update order
        $stmt = $pdo->prepare("
            UPDATE patient_lab_tests 
            SET results = ?, status = ?, result_date = CURRENT_DATE 
            WHERE id = ? AND status != 'completed'
        ");
        $result = $stmt->execute([$results, $status, $order_id]);
        
        if (!$result) {
            throw new Exception("Failed to update results");
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Check and create necessary tables
try {
    // Check if lab_departments table exists
    $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'lab_departments'");
    if (!$stmt->fetch()) {
        // Run the fix script
        require_once 'fix_laboratory.php';
    }
    
    // Get lab departments
    $stmt = $pdo->query("SELECT * FROM lab_departments WHERE status = 'active' ORDER BY name");
    $lab_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_test'])) {
    try {
        // Validate inputs
        $name = trim($_POST['name']);
        $lab_department_id = (int)$_POST['lab_department_id'];
        $cost = (float)$_POST['cost'];
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || $lab_department_id <= 0 || $cost <= 0) {
                throw new Exception("All fields are required and cost must be greater than zero.");
            }
            
            // Insert new test
            $stmt = $pdo->prepare("
                INSERT INTO lab_tests (name, lab_department_id, cost, description, status) 
                VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$name, $lab_department_id, $cost, $description]);
            
            $success = "Test added successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    elseif (isset($_POST['add_order'])) {
        try {
            // Validate inputs
            $patient_id = (int)$_POST['patient_id'];
            $doctor_id = (int)$_POST['doctor_id'];
            $test_id = (int)$_POST['test_id'];
            $test_date = $_POST['test_date'];
            $notes = trim($_POST['notes'] ?? '');
            
            if ($patient_id <= 0 || $test_id <= 0 || $doctor_id <= 0) {
                throw new Exception("Patient, doctor and test selection are required.");
            }
            
            // Insert new order
            $stmt = $pdo->prepare("
                INSERT INTO patient_lab_tests 
                (patient_id, lab_test_id, requested_by, test_date, status, notes) 
                VALUES (?, ?, ?, ?, 'requested', ?)
            ");
            $stmt->execute([$patient_id, $test_id, $doctor_id, $test_date, $notes]);
            
            $success = "Lab order created successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    elseif (isset($_POST['update_results'])) {
        try {
            $order_id = (int)$_POST['order_id'];
            $results = trim($_POST['results']);
            $status = $_POST['status'];
            
            if ($order_id <= 0 || empty($results)) {
                throw new Exception("Results cannot be empty.");
            }
            
            // Update order with results
            $stmt = $pdo->prepare("
                UPDATE patient_lab_tests 
                SET results = ?, status = ?, result_date = CURRENT_DATE 
                WHERE id = ?
            ");
            $stmt->execute([$results, $status, $order_id]);
            
            $success = "Test results updated successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    elseif (isset($_POST['edit_test'])) {
        try {
            // Validate inputs
            $test_id = (int)$_POST['test_id'];
            $name = trim($_POST['name']);
            $lab_department_id = (int)$_POST['lab_department_id'];
            $cost = (float)$_POST['cost'];
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name) || $lab_department_id <= 0 || $cost <= 0 || $test_id <= 0) {
                throw new Exception("All fields are required and cost must be greater than zero.");
            }
            
            // Update test
            $stmt = $pdo->prepare("
                UPDATE lab_tests 
                SET name = ?, lab_department_id = ?, cost = ?, description = ? 
                WHERE id = ?
            ");
            $stmt->execute([$name, $lab_department_id, $cost, $description, $test_id]);
            
            $success = "Test updated successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Remove the extra header include since it's part of the HTML structure below
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory - AKIRA HOSPITAL</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@x.x.x/dist/select2-bootstrap4.min.css">
    <style>
        :root {
            --primary-color: #0c4c8a;
            --secondary-color: #10aade;
            --accent-color: #21c286;
            --text-color: #333333;
            --light-bg: #f8f9fa;
            --border-radius: 10px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        /* Enhanced Sidebar Styles */
        .sidebar {
            background: linear-gradient(135deg, #1e293b 0%, #0c4c8a 100%);
            color: white;
            min-height: 100vh;
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            padding-top: 1rem;
            z-index: 1000;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            width: calc(100% - 250px);
            min-height: 100vh;
            background: var(--light-bg);
        }
        
        /* Enhanced Logo Styles */
        .logo-container {
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
        }
        
        .hospital-logo {
            color: #10b981;
            font-weight: bold;
            font-size: 1.75rem;
            margin-bottom: 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        /* Enhanced Navigation Styles */
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Enhanced Card Styles */
        .lab-card {
            background: white;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            margin-bottom: 1.5rem;
        }
        
        .lab-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .lab-card .card-body {
            padding: 1.5rem;
        }
        
        /* Enhanced Stats Card */
        .stats-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            height: 100%;
            transition: var(--transition);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            background: var(--primary-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Enhanced Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        
        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
        }
        
        /* Enhanced Status Badge Styles */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-requested {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .status-in-progress {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        /* Enhanced Action Buttons */
        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            transition: var(--transition);
            margin: 0 0.25rem;
        }
        
        .action-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Enhanced Footer */
        .footer {
            margin-left: 250px;
            padding: 1rem;
            background: white;
            border-top: 1px solid #dee2e6;
            position: fixed;
            bottom: 0;
            right: 0;
            width: calc(100% - 250px);
            z-index: 999;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
        }
        
        /* Adjust main content for footer */
        .main-content {
            padding-bottom: 80px;
        }
        
        /* Enhanced Modal Styles */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            background: var(--light-bg);
            border-bottom: 1px solid #dee2e6;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        
        .modal-footer {
            background: var(--light-bg);
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
        }
        
        /* Enhanced Form Controls */
        .form-control, .form-select {
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            border: 1px solid #dee2e6;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(12, 76, 138, 0.25);
        }
        
        /* Enhanced Select2 Styles */
        .select2-container {
            width: 100% !important;
        }
        
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #dee2e6;
            border-radius: var(--border-radius);
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            padding-left: 12px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #dee2e6;
            border-radius: var(--border-radius);
        }
        
        .select2-dropdown {
            border: 1px solid #dee2e6;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Modal Z-index fix for Select2 */
        .modal-dialog {
            z-index: 1050;
        }
        
        .select2-container--open {
            z-index: 1060;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content, .footer {
                margin-left: 0;
                width: 100%;
            }
            
            .show-sidebar .sidebar {
                width: 250px;
                transform: translateX(0);
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
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
                <a class="nav-link active" href="laboratory.php">
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
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">Laboratory Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Laboratory</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-toolbar gap-2">
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newTestModal">
                    <i class="fas fa-plus"></i> Add New Test
                </button>
                <button type="button" class="btn btn-success d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newOrderModal">
                    <i class="fas fa-flask"></i> New Lab Order
                </button>
            </div>
    </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-primary mb-2">Pending Orders</h6>
                            <h3 class="mb-0 fw-bold" id="pendingCount">
                                <?php
                                $stmt = $pdo->query("SELECT COUNT(*) FROM patient_lab_tests WHERE status = 'requested'");
                                echo $stmt->fetchColumn();
                                ?>
                            </h3>
                        </div>
                        <div class="stats-icon bg-primary-subtle">
                            <i class="fas fa-flask fa-lg text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Add more statistics cards here -->
    </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="lab-card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Quick Actions</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary action-btn" data-bs-toggle="modal" data-bs-target="#newTestModal">
                                <i class="fas fa-plus me-2"></i> New Test
                            </button>
                            <button type="button" class="btn btn-success action-btn" data-bs-toggle="modal" data-bs-target="#newOrderModal">
                                <i class="fas fa-flask me-2"></i> New Order
                            </button>
                            <button type="button" class="btn btn-info action-btn text-white" onclick="printDailyReport()">
                                <i class="fas fa-print me-2"></i> Daily Report
                        </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="lab-card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">Search & Filters</h5>
                <form id="searchForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Patient Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchPatient" placeholder="Search patient...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Test Type</label>
                        <select class="form-select" id="testType">
                            <option value="">All Tests</option>
                            <?php
                            $stmt = $pdo->query("SELECT DISTINCT name FROM lab_tests ORDER BY name");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='".htmlspecialchars($row['name'])."'>".htmlspecialchars($row['name'])."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <input type="date" class="form-control" id="dateRange">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="status">
                            <option value="">All Status</option>
                            <option value="requested">Requested</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lab Orders Table -->
        <div class="lab-card">
                <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title mb-0">Recent Lab Orders</h5>
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center">
                            <label class="me-2 text-nowrap">Show</label>
                            <select class="form-select form-select-sm w-auto">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <label class="ms-2">entries</label>
                        </div>
                        <div class="input-group input-group-sm" style="width: 200px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" placeholder="Search...">
                        </div>
                    </div>
                </div>

                    <div class="table-responsive">
                    <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Patient</th>
                                    <th>Test</th>
                                <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT plt.*, p.name as patient_name, lt.name as test_name,
                                       d.name as doctor_name
                                FROM patient_lab_tests plt
                                JOIN patients p ON plt.patient_id = p.id
                                JOIN lab_tests lt ON plt.lab_test_id = lt.id
                                JOIN doctors d ON plt.requested_by = d.id
                                ORDER BY plt.created_at DESC LIMIT 10
                            ");
                            while ($row = $stmt->fetch()):
                            ?>
                            <tr>
                                <td><strong>#<?php echo $row['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['test_name']); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['test_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                        <i class="fas fa-circle fa-sm"></i>
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                        </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-info btn-sm view-btn" data-id="<?php echo $row['id']; ?>" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($row['status'] !== 'completed'): ?>
                                        <button type="button" class="btn btn-primary btn-sm edit-btn" data-id="<?php echo $row['id']; ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-success btn-sm print-btn" data-id="<?php echo $row['id']; ?>" title="Print">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Showing <span class="fw-semibold">1</span> to <span class="fw-semibold">10</span> of <span class="fw-semibold">50</span> entries
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1">Previous</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid">
    <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> AKIRA HOSPITAL. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">Version 1.0.0</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/laboratory.js"></script>
    
    <script>
    // Initialize DataTables with enhanced features
    $(document).ready(function() {
        $('#labOrdersTable').DataTable({
            "order": [[0, "desc"]],
            "pageLength": 10,
            "responsive": true
        });

        // Real-time search functionality
        $('#searchForm input, #searchForm select').on('change', function() {
            filterResults();
        });
    });

    // Function to filter results based on search criteria
    function filterResults() {
        var patient = $('#searchPatient').val().toLowerCase();
        var testType = $('#testType').val().toLowerCase();
        var status = $('#status').val().toLowerCase();
        var date = $('#dateRange').val();

        $('#labOrdersTable tbody tr').each(function() {
            var row = $(this);
            var patientMatch = row.find('td:eq(1)').text().toLowerCase().includes(patient);
            var testMatch = testType === '' || row.find('td:eq(2)').text().toLowerCase().includes(testType);
            var statusMatch = status === '' || row.find('td:eq(5)').text().toLowerCase().includes(status);
            var dateMatch = !date || row.find('td:eq(4)').text().includes(date);

            if (patientMatch && testMatch && statusMatch && dateMatch) {
                row.show();
            } else {
                row.hide();
            }
        });
    }

    // Function to view test results
    function viewResults(id) {
        // Fetch order details using AJAX
        $.ajax({
            url: 'laboratory.php',
            type: 'GET',
            data: {
                action: 'get_order',
                id: id
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    // Populate modal fields
                    $('#order_id').val(data.id);
                    $('#patient_name').val(data.patient_name);
                    $('#test_name').val(data.test_name);
                    $('#doctor_name').val(data.doctor_name);
                    $('#test_date').val(data.test_date);
                    $('#results').val(data.results || '');
                    $('#status').val(data.status);
                    
                    // Disable editing for completed tests
                    const isCompleted = data.status === 'completed';
                    $('#results').prop('readonly', isCompleted);
                    $('#status').prop('disabled', isCompleted);
                    $('#saveResults').prop('disabled', isCompleted);
                    
                    // Show modal
                    $('#resultsModal').modal('show');
                } catch (e) {
                    alert('Error loading test results');
                }
            },
            error: function() {
                alert('Error loading test results');
            }
        });
    }

    // Handle form submission for updating results
    $('#updateResultsForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            order_id: $('#order_id').val(),
            results: $('#results').val(),
            status: $('#status').val(),
            action: 'update_results'
        };
        
        $.ajax({
            url: 'laboratory.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    // Close modal and refresh page
                    $('#resultsModal').modal('hide');
                    location.reload();
                } catch (e) {
                    alert('Error updating results');
                }
            },
            error: function() {
                alert('Error updating results');
            }
        });
    });

    // Handle printing of results
    $('#printResults').on('click', function() {
        const orderId = $('#order_id').val();
        window.open(`laboratory_print.php?id=${orderId}#print`, '_blank');
    });

    // Function to print daily report
    function printDailyReport() {
        window.open('laboratory_print.php?action=daily_report', '_blank');
    }

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Update test cost when a test is selected
    $('#test_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var cost = selectedOption.data('cost') || 0;
        $('#selectedTestCost').text('₹' + cost.toFixed(2));
    });

    // Initialize Select2 for better dropdown experience
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $('#newOrderModal')
        });
    });
    </script>

    <!-- New Test Modal -->
    <div class="modal fade" id="newTestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Laboratory Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="laboratory.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Test Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="lab_department_id" class="form-label">Department</label>
                            <select class="form-select" id="lab_department_id" name="lab_department_id" required>
                                <option value="">Select Department</option>
                            <?php foreach ($lab_departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                            <label for="cost" class="form-label">Cost</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="cost" name="cost" step="0.01" required>
                            </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_test" class="btn btn-primary">Add Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- New Order Modal -->
    <div class="modal fade" id="newOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Lab Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="laboratory.php" id="newOrderForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient</label>
                                <select class="form-select select2" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php
                                    $stmt = $pdo->query("SELECT id, name FROM patients ORDER BY name");
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . htmlspecialchars($row['id']) . "'>" . 
                                             htmlspecialchars($row['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Requesting Doctor</label>
                                <select class="form-select select2" name="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    <?php
                                    $stmt = $pdo->query("SELECT id, name FROM doctors WHERE status = 'active' ORDER BY name");
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . htmlspecialchars($row['id']) . "'>" . 
                                             htmlspecialchars($row['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Test</label>
                                <select class="form-select select2" name="test_id" required>
                                    <option value="">Select Test</option>
                                    <?php
                                    $stmt = $pdo->query("
                                        SELECT t.*, d.name as dept_name 
                                        FROM lab_tests t
                                        JOIN lab_departments d ON t.lab_department_id = d.id
                                        WHERE t.status = 'active'
                                        ORDER BY d.name, t.name
                                    ");
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . htmlspecialchars($row['id']) . "' " .
                                             "data-cost='" . htmlspecialchars($row['cost']) . "'>" .
                                             htmlspecialchars($row['dept_name'] . ' - ' . $row['name']) .
                                             " (₹" . number_format($row['cost'], 2) . ")</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Test Date</label>
                                <input type="date" class="form-control" name="test_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <div>
                                            <strong>Selected Test Cost:</strong>
                                            <span id="selectedTestCost">₹0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_order" class="btn btn-primary">Create Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View/Edit Results Modal -->
    <div class="modal fade" id="resultsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lab Test Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateResultsForm" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="order_id">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient Name</label>
                                <input type="text" class="form-control" id="patient_name" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Test Name</label>
                                <input type="text" class="form-control" id="test_name" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Doctor</label>
                                <input type="text" class="form-control" id="doctor_name" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Test Date</label>
                                <input type="text" class="form-control" id="test_date" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="results" class="form-label">Test Results</label>
                            <textarea class="form-control" id="results" name="results" rows="6" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="requested">Requested</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveResults">Save Results</button>
                        <button type="button" class="btn btn-success" id="printResults">Print Results</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Lab Order Modal -->
    <div class="modal fade" id="viewLabOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View Lab Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Order ID:</strong> <span id="viewOrderId"></span></p>
                            <p><strong>Patient Name:</strong> <span id="viewPatientName"></span></p>
                            <p><strong>Test Name:</strong> <span id="viewTestName"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Doctor:</strong> <span id="viewDoctorName"></span></p>
                            <p><strong>Test Date:</strong> <span id="viewTestDate"></span></p>
                            <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h6>Test Results:</h6>
                            <pre id="viewResults" class="p-3 bg-light"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success print-btn" onclick="window.open('laboratory_print.php?id=' + $('#viewOrderId').text() + '#print', '_blank');">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Lab Order Modal -->
    <div class="modal fade" id="editLabOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalTitle">Edit Lab Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editLabResultsForm" onsubmit="event.preventDefault(); saveLabResults('editLabResultsForm');">
                    <div class="modal-body">
                        <input type="hidden" id="editOrderId" name="order_id">
                        <input type="hidden" name="action" value="update_results">
                        
                        <div class="mb-3">
                            <label for="editResults" class="form-label">Test Results</label>
                            <textarea class="form-control" id="editResults" name="results" rows="6" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editStatus" class="form-label">Status</label>
                            <select class="form-select" id="editStatus" name="status" required>
                                <option value="requested">Requested</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer" class="position-fixed top-0 start-50 translate-middle-x" style="z-index: 1050;"></div>
</body>
</html> 