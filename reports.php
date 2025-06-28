<?php
/**
 * AKIRA HOSPITAL Management System
 * Reports Generation Page
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

// Get report type
$report_type = isset($_GET['type']) ? $_GET['type'] : 'appointment';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t'); // Last day of current month
$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;

// Get list of doctors for filter
$doctors = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctors'")) {
        $doctors = db_get_rows("SELECT id, name FROM doctors ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
}

// Get list of departments for filter
$departments = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'departments'")) {
        $departments = db_get_rows("SELECT id, name FROM departments ORDER BY name ASC");
    }
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Function to get appointment statistics
function getAppointmentStats($date_from, $date_to, $doctor_id = 0) {
    $params = [':date_from' => $date_from, ':date_to' => $date_to];
    $doctor_filter = "";
    
    if ($doctor_id > 0) {
        $doctor_filter = " AND a.doctor_id = :doctor_id";
        $params[':doctor_id'] = $doctor_id;
    }
    
    $query = "SELECT 
                COUNT(*) as total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN a.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show
              FROM appointments a
              WHERE a.appointment_date BETWEEN :date_from AND :date_to" . $doctor_filter;
    
    return db_get_row($query, $params);
}

// Function to get patient statistics
function getPatientStats($date_from, $date_to) {
    $query = "SELECT 
                COUNT(*) as total_patients,
                SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male_patients,
                SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female_patients,
                SUM(CASE WHEN DATE(created_at) BETWEEN :date_from AND :date_to THEN 1 ELSE 0 END) as new_patients
              FROM patients";
    
    return db_get_row($query, [':date_from' => $date_from, ':date_to' => $date_to]);
}

// Function to get revenue statistics
function getRevenueStats($date_from, $date_to) {
    $query = "SELECT 
                SUM(total_amount) as total_revenue,
                SUM(paid_amount) as collected_amount,
                SUM(total_amount - paid_amount) as pending_amount,
                COUNT(*) as invoice_count,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_invoices
              FROM invoices
              WHERE invoice_date BETWEEN :date_from AND :date_to";
    
    return db_get_row($query, [':date_from' => $date_from, ':date_to' => $date_to]);
}

// Function to get department statistics
function getDepartmentStats($date_from, $date_to, $department_id = 0) {
    $params = [':date_from' => $date_from, ':date_to' => $date_to];
    $department_filter = "";
    
    if ($department_id > 0) {
        $department_filter = " AND d.department_id = :department_id";
        $params[':department_id'] = $department_id;
    }
    
    $query = "SELECT 
                dept.name as department_name,
                COUNT(a.id) as appointment_count
              FROM departments dept
              LEFT JOIN doctors d ON d.department_id = dept.id
              LEFT JOIN appointments a ON a.doctor_id = d.id AND a.appointment_date BETWEEN :date_from AND :date_to
              WHERE 1=1" . $department_filter . "
              GROUP BY dept.id, dept.name
              ORDER BY appointment_count DESC";
    
    return db_get_rows($query, $params);
}

// Function to get medicine usage statistics
function getMedicineStats($date_from, $date_to) {
    $query = "SELECT 
                m.name as medicine_name,
                mc.name as category_name,
                COUNT(pi.id) as prescription_count,
                SUM(pi.quantity) as total_quantity
              FROM medicines m
              LEFT JOIN medicine_categories mc ON m.category_id = mc.id
              LEFT JOIN prescription_items pi ON pi.medicine_id = m.id
              LEFT JOIN prescriptions p ON pi.prescription_id = p.id AND p.prescription_date BETWEEN :date_from AND :date_to
              GROUP BY m.id, m.name, mc.name
              ORDER BY total_quantity DESC
              LIMIT 20";
    
    return db_get_rows($query, [':date_from' => $date_from, ':date_to' => $date_to]);
}

// Function to get doctor performance statistics
function getDoctorPerformanceStats($date_from, $date_to) {
    $query = "SELECT 
                d.name as doctor_name,
                dept.name as department_name,
                COUNT(a.id) as appointment_count,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show
              FROM doctors d
              LEFT JOIN departments dept ON d.department_id = dept.id
              LEFT JOIN appointments a ON a.doctor_id = d.id AND a.appointment_date BETWEEN :date_from AND :date_to
              GROUP BY d.id, d.name, dept.name
              ORDER BY appointment_count DESC";
    
    return db_get_rows($query, [':date_from' => $date_from, ':date_to' => $date_to]);
}

// Function to get lab test statistics
function getLabTestStats($date_from, $date_to) {
    $query = "SELECT 
                lt.name as test_name,
                tc.name as category_name,
                COUNT(lo.id) as order_count,
                SUM(lt.price) as total_revenue
              FROM lab_tests lt
              LEFT JOIN test_categories tc ON lt.category_id = tc.id
              LEFT JOIN lab_orders lo ON lo.test_id = lt.id AND lo.order_date BETWEEN :date_from AND :date_to
              GROUP BY lt.id, lt.name, tc.name
              ORDER BY order_count DESC";
    
    return db_get_rows($query, [':date_from' => $date_from, ':date_to' => $date_to]);
}

// Get report data based on type
$report_data = [];
$report_title = "";

if ($report_type === 'appointment') {
    $report_data = getAppointmentStats($date_from, $date_to, $doctor_id);
    $report_title = "Appointment Statistics";
} elseif ($report_type === 'patient') {
    $report_data = getPatientStats($date_from, $date_to);
    $report_title = "Patient Statistics";
} elseif ($report_type === 'revenue') {
    $report_data = getRevenueStats($date_from, $date_to);
    $report_title = "Revenue Statistics";
} elseif ($report_type === 'department') {
    $report_data = getDepartmentStats($date_from, $date_to, $department_id);
    $report_title = "Department Statistics";
} elseif ($report_type === 'medicine') {
    $report_data = getMedicineStats($date_from, $date_to);
    $report_title = "Medicine Usage Statistics";
} elseif ($report_type === 'doctor') {
    $report_data = getDoctorPerformanceStats($date_from, $date_to);
    $report_title = "Doctor Performance";
} elseif ($report_type === 'lab') {
    $report_data = getLabTestStats($date_from, $date_to);
    $report_title = "Laboratory Test Statistics";
}

// Format date range for display
$formatted_date_from = date('d M Y', strtotime($date_from));
$formatted_date_to = date('d M Y', strtotime($date_to));
$date_range = "{$formatted_date_from} to {$formatted_date_to}";

// Handle export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($report_type === 'appointment') {
        fputcsv($output, ['Report', 'Appointment Statistics']);
        fputcsv($output, ['Date Range', $date_range]);
        fputcsv($output, ['Total Appointments', $report_data['total_appointments'] ?? 0]);
        fputcsv($output, ['Completed', $report_data['completed'] ?? 0]);
        fputcsv($output, ['Scheduled', $report_data['scheduled'] ?? 0]);
        fputcsv($output, ['Cancelled', $report_data['cancelled'] ?? 0]);
        fputcsv($output, ['No Show', $report_data['no_show'] ?? 0]);
    } elseif ($report_type === 'patient') {
        fputcsv($output, ['Report', 'Patient Statistics']);
        fputcsv($output, ['Date Range', $date_range]);
        fputcsv($output, ['Total Patients', $report_data['total_patients'] ?? 0]);
        fputcsv($output, ['Male Patients', $report_data['male_patients'] ?? 0]);
        fputcsv($output, ['Female Patients', $report_data['female_patients'] ?? 0]);
        fputcsv($output, ['New Patients', $report_data['new_patients'] ?? 0]);
    } elseif ($report_type === 'revenue') {
        fputcsv($output, ['Report', 'Revenue Statistics']);
        fputcsv($output, ['Date Range', $date_range]);
        fputcsv($output, ['Total Revenue (₹)', $report_data['total_revenue'] ?? 0]);
        fputcsv($output, ['Collected Amount (₹)', $report_data['collected_amount'] ?? 0]);
        fputcsv($output, ['Pending Amount (₹)', $report_data['pending_amount'] ?? 0]);
        fputcsv($output, ['Invoice Count', $report_data['invoice_count'] ?? 0]);
        fputcsv($output, ['Paid Invoices', $report_data['paid_invoices'] ?? 0]);
    } elseif ($report_type === 'department') {
        fputcsv($output, ['Report', 'Department Statistics']);
        fputcsv($output, ['Date Range', $date_range]);
        fputcsv($output, ['Department', 'Appointment Count']);
        
        foreach ($report_data as $row) {
            fputcsv($output, [$row['department_name'], $row['appointment_count']]);
        }
    } elseif ($report_type === 'medicine') {
        fputcsv($output, ['Report', 'Medicine Usage Statistics']);
        fputcsv($output, ['Date Range', $date_range]);
        fputcsv($output, ['Medicine Name', 'Category', 'Prescription Count', 'Total Quantity']);
        
        foreach ($report_data as $row) {
            fputcsv($output, [
                $row['medicine_name'],
                $row['category_name'] ?? 'Uncategorized',
                $row['prescription_count'],
                $row['total_quantity']
            ]);
        }
    } elseif ($report_type === 'doctor') {
        fputcsv($output, ['Report', 'Doctor Performance']);
        fputcsv($output, ['Date Range', $date_range]);
        fputcsv($output, ['Doctor Name', 'Department', 'Appointment Count', 'Completed', 'Cancelled', 'No Show']);
        
        foreach ($report_data as $row) {
            fputcsv($output, [
                $row['doctor_name'],
                $row['department_name'] ?? 'Unassigned',
                $row['appointment_count'],
                $row['completed'],
                $row['cancelled'],
                $row['no_show']
            ]);
        }
    } elseif ($report_type === 'lab') {
        fputcsv($output, ['Report', 'Laboratory Test Statistics']);
        fputcsv($output, ['Date Range', $date_range]);
        fputcsv($output, ['Test Name', 'Category', 'Order Count', 'Total Revenue (₹)']);
        
        foreach ($report_data as $row) {
            fputcsv($output, [
                $row['test_name'],
                $row['category_name'] ?? 'Uncategorized',
                $row['order_count'],
                $row['total_revenue']
            ]);
        }
    }
    
    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - AKIRA HOSPITAL</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #083b6f;
            border-color: #083b6f;
        }
        
        .report-filter-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            padding: 1.25rem;
        }
        
        .report-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            padding: 1.25rem;
        }
        
        .report-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .stat-card .stat-label {
            font-size: 1rem;
            color: #6c757d;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .table-responsive {
            margin-top: 1rem;
        }
        
        .export-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
            text-decoration: none;
            background-color: #6c757d;
            color: white;
            border: none;
        }
        
        .export-btn:hover {
            background-color: #5a6268;
            color: white;
        }
        
        .export-btn i {
            margin-right: 0.5rem;
        }
        
        @media print {
            .sidebar, .topbar, .report-filter-card, .no-print {
                display: none !important;
            }
            
            body {
                background-color: white;
            }
            
            .container-fluid {
                padding: 0;
            }
            
            .report-card {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
            
            .chart-container {
                height: 400px;
                page-break-inside: avoid;
            }
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
                        <a class="nav-link active" href="reports.php">
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
                        <h4 class="mb-0">Reports</h4>
                    </div>
                    <div class="user-welcome">
                        Welcome, <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span> (<?php echo htmlspecialchars($admin_role); ?>)
                    </div>
                </div>
                
                <!-- Report Filter Card -->
                <div class="report-filter-card">
                    <form method="get" action="reports.php" class="row g-3">
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="type">
                                <option value="appointment" <?php echo ($report_type === 'appointment') ? 'selected' : ''; ?>>Appointment Statistics</option>
                                <option value="patient" <?php echo ($report_type === 'patient') ? 'selected' : ''; ?>>Patient Statistics</option>
                                <option value="revenue" <?php echo ($report_type === 'revenue') ? 'selected' : ''; ?>>Revenue Statistics</option>
                                <option value="department" <?php echo ($report_type === 'department') ? 'selected' : ''; ?>>Department Statistics</option>
                                <option value="medicine" <?php echo ($report_type === 'medicine') ? 'selected' : ''; ?>>Medicine Usage</option>
                                <option value="doctor" <?php echo ($report_type === 'doctor') ? 'selected' : ''; ?>>Doctor Performance</option>
                                <option value="lab" <?php echo ($report_type === 'lab') ? 'selected' : ''; ?>>Laboratory Test Statistics</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="col-md-3 doctor-filter" style="display: <?php echo ($report_type === 'appointment') ? 'block' : 'none'; ?>">
                            <label for="doctor_id" class="form-label">Doctor</label>
                            <select class="form-select" id="doctor_id" name="doctor_id">
                                <option value="0">All Doctors</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>" <?php echo ($doctor_id == $doctor['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doctor['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 department-filter" style="display: <?php echo ($report_type === 'department') ? 'block' : 'none'; ?>">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="0">All Departments</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>" <?php echo ($department_id == $department['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <button type="button" id="printReportBtn" class="btn btn-secondary ms-2">
                                <i class="fas fa-print me-1"></i> Print Report
                            </button>
                            <a href="reports.php?type=<?php echo $report_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&doctor_id=<?php echo $doctor_id; ?>&department_id=<?php echo $department_id; ?>&export=csv" class="btn btn-success ms-2">
                                <i class="fas fa-file-csv me-1"></i> Export to CSV
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Report Content -->
                <div class="report-card">
                    <div class="report-card-header">
                        <h5><?php echo $report_title; ?></h5>
                        <div>
                            <span class="badge bg-info">
                                <i class="fas fa-calendar me-1"></i> <?php echo $date_range; ?>
                            </span>
                            <?php if ($report_type === 'appointment' && $doctor_id > 0): ?>
                                <?php 
                                $doctor_name = '';
                                foreach ($doctors as $doctor) {
                                    if ($doctor['id'] == $doctor_id) {
                                        $doctor_name = $doctor['name'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="badge bg-primary ms-1">
                                    <i class="fas fa-user-md me-1"></i> <?php echo htmlspecialchars($doctor_name); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($report_type === 'department' && $department_id > 0): ?>
                                <?php 
                                $department_name = '';
                                foreach ($departments as $department) {
                                    if ($department['id'] == $department_id) {
                                        $department_name = $department['name'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="badge bg-primary ms-1">
                                    <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($department_name); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Appointment Statistics -->
                    <?php if ($report_type === 'appointment'): ?>
                        <div class="row">
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['total_appointments'] ?? 0; ?></div>
                                    <div class="stat-label">Total Appointments</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['completed'] ?? 0; ?></div>
                                    <div class="stat-label">Completed</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['scheduled'] ?? 0; ?></div>
                                    <div class="stat-label">Scheduled</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['cancelled'] ?? 0; ?></div>
                                    <div class="stat-label">Cancelled</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['no_show'] ?? 0; ?></div>
                                    <div class="stat-label">No Show</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value">
                                        <?php 
                                        $completion_rate = 0;
                                        if (($report_data['total_appointments'] ?? 0) > 0) {
                                            $completion_rate = round(($report_data['completed'] / $report_data['total_appointments']) * 100);
                                        }
                                        echo $completion_rate . '%';
                                        ?>
                                    </div>
                                    <div class="stat-label">Completion Rate</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6>Appointment Status Distribution</h6>
                                <div class="chart-container">
                                    <canvas id="appointmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var ctx = document.getElementById('appointmentChart').getContext('2d');
                                var appointmentChart = new Chart(ctx, {
                                    type: 'pie',
                                    data: {
                                        labels: ['Completed', 'Scheduled', 'Cancelled', 'No Show'],
                                        datasets: [{
                                            data: [
                                                <?php echo $report_data['completed'] ?? 0; ?>,
                                                <?php echo $report_data['scheduled'] ?? 0; ?>,
                                                <?php echo $report_data['cancelled'] ?? 0; ?>,
                                                <?php echo $report_data['no_show'] ?? 0; ?>
                                            ],
                                            backgroundColor: [
                                                'rgba(40, 167, 69, 0.7)',
                                                'rgba(0, 123, 255, 0.7)',
                                                'rgba(220, 53, 69, 0.7)',
                                                'rgba(255, 193, 7, 0.7)'
                                            ],
                                            borderColor: [
                                                'rgba(40, 167, 69, 1)',
                                                'rgba(0, 123, 255, 1)',
                                                'rgba(220, 53, 69, 1)',
                                                'rgba(255, 193, 7, 1)'
                                            ],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'right'
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    
                    <!-- Patient Statistics -->
                    <?php elseif ($report_type === 'patient'): ?>
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['total_patients'] ?? 0; ?></div>
                                    <div class="stat-label">Total Patients</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['new_patients'] ?? 0; ?></div>
                                    <div class="stat-label">New Patients</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['male_patients'] ?? 0; ?></div>
                                    <div class="stat-label">Male Patients</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['female_patients'] ?? 0; ?></div>
                                    <div class="stat-label">Female Patients</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6>Gender Distribution</h6>
                                <div class="chart-container">
                                    <canvas id="genderChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Patient Growth</h6>
                                <div class="chart-container">
                                    <canvas id="patientGrowthChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Gender Distribution Chart
                                var genderCtx = document.getElementById('genderChart').getContext('2d');
                                var genderChart = new Chart(genderCtx, {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Male', 'Female', 'Other'],
                                        datasets: [{
                                            data: [
                                                <?php echo $report_data['male_patients'] ?? 0; ?>,
                                                <?php echo $report_data['female_patients'] ?? 0; ?>,
                                                <?php echo ($report_data['total_patients'] ?? 0) - ($report_data['male_patients'] ?? 0) - ($report_data['female_patients'] ?? 0); ?>
                                            ],
                                            backgroundColor: [
                                                'rgba(0, 123, 255, 0.7)',
                                                'rgba(255, 99, 132, 0.7)',
                                                'rgba(128, 128, 128, 0.7)'
                                            ],
                                            borderColor: [
                                                'rgba(0, 123, 255, 1)',
                                                'rgba(255, 99, 132, 1)',
                                                'rgba(128, 128, 128, 1)'
                                            ],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'right'
                                            }
                                        }
                                    }
                                });
                                
                                // Patient Growth Chart
                                var growthCtx = document.getElementById('patientGrowthChart').getContext('2d');
                                var growthChart = new Chart(growthCtx, {
                                    type: 'bar',
                                    data: {
                                        labels: ['Total Patients', 'New Patients in Period'],
                                        datasets: [{
                                            label: 'Patients',
                                            data: [
                                                <?php echo $report_data['total_patients'] ?? 0; ?>,
                                                <?php echo $report_data['new_patients'] ?? 0; ?>
                                            ],
                                            backgroundColor: [
                                                'rgba(23, 162, 184, 0.7)',
                                                'rgba(40, 167, 69, 0.7)'
                                            ],
                                            borderColor: [
                                                'rgba(23, 162, 184, 1)',
                                                'rgba(40, 167, 69, 1)'
                                            ],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    
                    <!-- Revenue Statistics -->
                    <?php elseif ($report_type === 'revenue'): ?>
                        <div class="row">
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value">₹<?php echo number_format($report_data['total_revenue'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Total Revenue</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value">₹<?php echo number_format($report_data['collected_amount'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Collected Amount</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value">₹<?php echo number_format($report_data['pending_amount'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Pending Amount</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['invoice_count'] ?? 0; ?></div>
                                    <div class="stat-label">Total Invoices</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $report_data['paid_invoices'] ?? 0; ?></div>
                                    <div class="stat-label">Paid Invoices</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="stat-card">
                                    <div class="stat-value">
                                        <?php 
                                        $collection_rate = 0;
                                        if (($report_data['total_revenue'] ?? 0) > 0) {
                                            $collection_rate = round(($report_data['collected_amount'] / $report_data['total_revenue']) * 100);
                                        }
                                        echo $collection_rate . '%';
                                        ?>
                                    </div>
                                    <div class="stat-label">Collection Rate</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6>Revenue Distribution</h6>
                                <div class="chart-container">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Invoice Payment Status</h6>
                                <div class="chart-container">
                                    <canvas id="invoiceStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Revenue Distribution Chart
                                var revenueCtx = document.getElementById('revenueChart').getContext('2d');
                                var revenueChart = new Chart(revenueCtx, {
                                    type: 'pie',
                                    data: {
                                        labels: ['Collected', 'Pending'],
                                        datasets: [{
                                            data: [
                                                <?php echo $report_data['collected_amount'] ?? 0; ?>,
                                                <?php echo $report_data['pending_amount'] ?? 0; ?>
                                            ],
                                            backgroundColor: [
                                                'rgba(40, 167, 69, 0.7)',
                                                'rgba(255, 193, 7, 0.7)'
                                            ],
                                            borderColor: [
                                                'rgba(40, 167, 69, 1)',
                                                'rgba(255, 193, 7, 1)'
                                            ],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'right'
                                            }
                                        }
                                    }
                                });
                                
                                // Invoice Status Chart
                                var invoiceCtx = document.getElementById('invoiceStatusChart').getContext('2d');
                                var invoiceChart = new Chart(invoiceCtx, {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Paid', 'Unpaid'],
                                        datasets: [{
                                            data: [
                                                <?php echo $report_data['paid_invoices'] ?? 0; ?>,
                                                <?php echo ($report_data['invoice_count'] ?? 0) - ($report_data['paid_invoices'] ?? 0); ?>
                                            ],
                                            backgroundColor: [
                                                'rgba(40, 167, 69, 0.7)',
                                                'rgba(220, 53, 69, 0.7)'
                                            ],
                                            borderColor: [
                                                'rgba(40, 167, 69, 1)',
                                                'rgba(220, 53, 69, 1)'
                                            ],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'right'
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    
                    <!-- Department Statistics -->
                    <?php elseif ($report_type === 'department'): ?>
                        <?php if (empty($report_data)): ?>
                            <div class="alert alert-info">No data available for the selected filters.</div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-12">
                                    <h6>Department Appointment Distribution</h6>
                                    <div class="chart-container">
                                        <canvas id="departmentChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive mt-4">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Appointment Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_appointments = 0;
                                        foreach ($report_data as $row) {
                                            $total_appointments += $row['appointment_count'];
                                        }
                                        
                                        foreach ($report_data as $row): 
                                            $percentage = ($total_appointments > 0) ? round(($row['appointment_count'] / $total_appointments) * 100, 2) : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['department_name'] ?: 'Unassigned'); ?></td>
                                                <td><?php echo $row['appointment_count']; ?></td>
                                                <td><?php echo $percentage; ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var departmentCtx = document.getElementById('departmentChart').getContext('2d');
                                    var departmentChart = new Chart(departmentCtx, {
                                        type: 'bar',
                                        data: {
                                            labels: [
                                                <?php 
                                                // Ensure unique department names
                                                $seen_departments = [];
                                                
                                                foreach ($report_data as $row) {
                                                    $dept_name = $row['department_name'] ?: 'Unassigned';
                                                    if (!in_array($dept_name, $seen_departments)) {
                                                        $seen_departments[] = $dept_name;
                                                        echo "'" . addslashes($dept_name) . "', ";
                                                    }
                                                }
                                                ?>
                                            ],
                                            datasets: [{
                                                label: 'Appointment Count',
                                                data: [
                                                    <?php 
                                                    // Reset seen departments for data array
                                                    $seen_departments = [];
                                                    $department_counts = [];
                                                    
                                                    // Group appointment counts by department
                                                    foreach ($report_data as $row) {
                                                        $dept_name = $row['department_name'] ?: 'Unassigned';
                                                        if (!in_array($dept_name, $seen_departments)) {
                                                            $seen_departments[] = $dept_name;
                                                            $department_counts[] = $row['appointment_count'];
                                                        }
                                                    }
                                                    
                                                    // Output appointment counts in the same order as labels
                                                    foreach ($department_counts as $count) {
                                                        echo $count . ", ";
                                                    }
                                                    ?>
                                                ],
                                                backgroundColor: 'rgba(0, 123, 255, 0.7)',
                                                borderColor: 'rgba(0, 123, 255, 1)',
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                y: {
                                                    beginAtZero: true
                                                }
                                            }
                                        }
                                    });
                                });
                            </script>
                        <?php endif; ?>
                    
                    <!-- Medicine Usage Statistics -->
                    <?php elseif ($report_type === 'medicine'): ?>
                        <?php if (empty($report_data)): ?>
                            <div class="alert alert-info">No data available for the selected filters.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Medicine Name</th>
                                            <th>Category</th>
                                            <th>Prescription Count</th>
                                            <th>Total Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['medicine_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['category_name'] ?: 'Uncategorized'); ?></td>
                                                <td><?php echo $row['prescription_count']; ?></td>
                                                <td><?php echo $row['total_quantity'] ?: 0; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6>Top 10 Medicines by Usage</h6>
                                    <div class="chart-container">
                                        <canvas id="medicineChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var medicineCtx = document.getElementById('medicineChart').getContext('2d');
                                    var medicineChart = new Chart(medicineCtx, {
                                        type: 'horizontalBar',
                                        data: {
                                            labels: [
                                                <?php 
                                                $count = 0;
                                                foreach ($report_data as $row) {
                                                    if ($count++ >= 10) break;
                                                    echo "'" . addslashes($row['medicine_name']) . "', ";
                                                }
                                                ?>
                                            ],
                                            datasets: [{
                                                label: 'Total Quantity Used',
                                                data: [
                                                    <?php 
                                                    $count = 0;
                                                    foreach ($report_data as $row) {
                                                        if ($count++ >= 10) break;
                                                        echo ($row['total_quantity'] ?: 0) . ", ";
                                                    }
                                                    ?>
                                                ],
                                                backgroundColor: 'rgba(23, 162, 184, 0.7)',
                                                borderColor: 'rgba(23, 162, 184, 1)',
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            indexAxis: 'y',
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                x: {
                                                    beginAtZero: true
                                                }
                                            }
                                        }
                                    });
                                });
                            </script>
                        <?php endif; ?>
                    
                    <!-- Doctor Performance -->
                    <?php elseif ($report_type === 'doctor'): ?>
                        <?php if (empty($report_data)): ?>
                            <div class="alert alert-info">No data available for the selected filters.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Doctor Name</th>
                                            <th>Department</th>
                                            <th>Total Appointments</th>
                                            <th>Completed</th>
                                            <th>Cancelled</th>
                                            <th>No Show</th>
                                            <th>Completion Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['department_name'] ?: 'Unassigned'); ?></td>
                                                <td><?php echo $row['appointment_count']; ?></td>
                                                <td><?php echo $row['completed']; ?></td>
                                                <td><?php echo $row['cancelled']; ?></td>
                                                <td><?php echo $row['no_show']; ?></td>
                                                <td>
                                                    <?php 
                                                    $completion_rate = 0;
                                                    if ($row['appointment_count'] > 0) {
                                                        $completion_rate = round(($row['completed'] / $row['appointment_count']) * 100);
                                                    }
                                                    echo $completion_rate . '%';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6>Top 10 Doctors by Appointment Volume</h6>
                                    <div class="chart-container">
                                        <canvas id="doctorChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var doctorCtx = document.getElementById('doctorChart').getContext('2d');
                                    var doctorChart = new Chart(doctorCtx, {
                                        type: 'bar',
                                        data: {
                                            labels: [
                                                <?php 
                                                $count = 0;
                                                foreach ($report_data as $row) {
                                                    if ($count++ >= 10) break;
                                                    echo "'" . addslashes($row['doctor_name']) . "', ";
                                                }
                                                ?>
                                            ],
                                            datasets: [{
                                                label: 'Completed',
                                                data: [
                                                    <?php 
                                                    $count = 0;
                                                    foreach ($report_data as $row) {
                                                        if ($count++ >= 10) break;
                                                        echo $row['completed'] . ", ";
                                                    }
                                                    ?>
                                                ],
                                                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                                                borderColor: 'rgba(40, 167, 69, 1)',
                                                borderWidth: 1
                                            }, {
                                                label: 'Cancelled',
                                                data: [
                                                    <?php 
                                                    $count = 0;
                                                    foreach ($report_data as $row) {
                                                        if ($count++ >= 10) break;
                                                        echo $row['cancelled'] . ", ";
                                                    }
                                                    ?>
                                                ],
                                                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                                                borderColor: 'rgba(220, 53, 69, 1)',
                                                borderWidth: 1
                                            }, {
                                                label: 'No Show',
                                                data: [
                                                    <?php 
                                                    $count = 0;
                                                    foreach ($report_data as $row) {
                                                        if ($count++ >= 10) break;
                                                        echo $row['no_show'] . ", ";
                                                    }
                                                    ?>
                                                ],
                                                backgroundColor: 'rgba(255, 193, 7, 0.7)',
                                                borderColor: 'rgba(255, 193, 7, 1)',
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                x: {
                                                    stacked: true
                                                },
                                                y: {
                                                    stacked: true,
                                                    beginAtZero: true
                                                }
                                            }
                                        }
                                    });
                                });
                            </script>
                        <?php endif; ?>
                    
                    <!-- Laboratory Test Statistics -->
                    <?php elseif ($report_type === 'lab'): ?>
                        <?php if (empty($report_data)): ?>
                            <div class="alert alert-info">No data available for the selected filters.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Test Name</th>
                                            <th>Category</th>
                                            <th>Order Count</th>
                                            <th>Revenue (₹)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['test_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['category_name'] ?: 'Uncategorized'); ?></td>
                                                <td><?php echo $row['order_count']; ?></td>
                                                <td>₹<?php echo number_format($row['total_revenue'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h6>Top Tests by Order Count</h6>
                                    <div class="chart-container">
                                        <canvas id="testOrderChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Top Tests by Revenue</h6>
                                    <div class="chart-container">
                                        <canvas id="testRevenueChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Test Order Chart
                                    var orderCtx = document.getElementById('testOrderChart').getContext('2d');
                                    var orderChart = new Chart(orderCtx, {
                                        type: 'pie',
                                        data: {
                                            labels: [
                                                <?php 
                                                $count = 0;
                                                foreach ($report_data as $row) {
                                                    if ($count++ >= 5) break;
                                                    echo "'" . addslashes($row['test_name']) . "', ";
                                                }
                                                if (count($report_data) > 5) {
                                                    echo "'Others'";
                                                }
                                                ?>
                                            ],
                                            datasets: [{
                                                data: [
                                                    <?php 
                                                    $count = 0;
                                                    $other_orders = 0;
                                                    foreach ($report_data as $index => $row) {
                                                        if ($count++ < 5) {
                                                            echo $row['order_count'] . ", ";
                                                        } else {
                                                            $other_orders += $row['order_count'];
                                                        }
                                                    }
                                                    if (count($report_data) > 5) {
                                                        echo $other_orders;
                                                    }
                                                    ?>
                                                ],
                                                backgroundColor: [
                                                    'rgba(0, 123, 255, 0.7)',
                                                    'rgba(40, 167, 69, 0.7)',
                                                    'rgba(255, 193, 7, 0.7)',
                                                    'rgba(220, 53, 69, 0.7)',
                                                    'rgba(23, 162, 184, 0.7)',
                                                    'rgba(108, 117, 125, 0.7)'
                                                ],
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: {
                                                legend: {
                                                    position: 'right'
                                                }
                                            }
                                        }
                                    });
                                    
                                    // Test Revenue Chart
                                    var revenueCtx = document.getElementById('testRevenueChart').getContext('2d');
                                    var revenueChart = new Chart(revenueCtx, {
                                        type: 'doughnut',
                                        data: {
                                            labels: [
                                                <?php 
                                                // Sort by revenue
                                                usort($report_data, function($a, $b) {
                                                    return $b['total_revenue'] - $a['total_revenue'];
                                                });
                                                
                                                $count = 0;
                                                foreach ($report_data as $row) {
                                                    if ($count++ >= 5) break;
                                                    echo "'" . addslashes($row['test_name']) . "', ";
                                                }
                                                if (count($report_data) > 5) {
                                                    echo "'Others'";
                                                }
                                                ?>
                                            ],
                                            datasets: [{
                                                data: [
                                                    <?php 
                                                    $count = 0;
                                                    $other_revenue = 0;
                                                    foreach ($report_data as $index => $row) {
                                                        if ($count++ < 5) {
                                                            echo $row['total_revenue'] . ", ";
                                                        } else {
                                                            $other_revenue += $row['total_revenue'];
                                                        }
                                                    }
                                                    if (count($report_data) > 5) {
                                                        echo $other_revenue;
                                                    }
                                                    ?>
                                                ],
                                                backgroundColor: [
                                                    'rgba(40, 167, 69, 0.7)',
                                                    'rgba(0, 123, 255, 0.7)',
                                                    'rgba(255, 193, 7, 0.7)',
                                                    'rgba(23, 162, 184, 0.7)',
                                                    'rgba(220, 53, 69, 0.7)',
                                                    'rgba(108, 117, 125, 0.7)'
                                                ],
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: {
                                                legend: {
                                                    position: 'right'
                                                }
                                            }
                                        }
                                    });
                                });
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for report features -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show/hide filters based on report type
            const reportTypeSelect = document.getElementById('report_type');
            const doctorFilter = document.querySelector('.doctor-filter');
            const departmentFilter = document.querySelector('.department-filter');
            
            reportTypeSelect.addEventListener('change', function() {
                if (this.value === 'appointment') {
                    doctorFilter.style.display = 'block';
                    departmentFilter.style.display = 'none';
                } else if (this.value === 'department') {
                    doctorFilter.style.display = 'none';
                    departmentFilter.style.display = 'block';
                } else {
                    doctorFilter.style.display = 'none';
                    departmentFilter.style.display = 'none';
                }
            });
            
            // Print report functionality
            const printReportBtn = document.getElementById('printReportBtn');
            if (printReportBtn) {
                printReportBtn.addEventListener('click', function() {
                    window.print();
                });
            }
        });
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>