<?php
/**
 * AKIRA HOSPITAL Management System
 * Dashboard Page for XAMPP PostgreSQL
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

// Get counts from database (with error handling)
try {
    // Count patients
    $patient_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'patients'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM patients");
        $patient_count = $result['count'] ?? 0;
    }
    
    // Count doctors
    $doctor_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctors'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM doctors");
        $doctor_count = $result['count'] ?? 0;
    }
    
    // Count appointments
    $appointment_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'appointments'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM appointments");
        $appointment_count = $result['count'] ?? 0;
    }
    
    // Count staff
    $staff_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'staff'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM staff");
        $staff_count = $result['count'] ?? 0;
    }
    
    // Count beds
    $beds_count = 0;
    $available_beds_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'beds'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM beds");
        $beds_count = $result['count'] ?? 0;
        
        $result = db_get_row("SELECT COUNT(*) as count FROM beds WHERE status = 'available' AND is_active = true");
        $available_beds_count = $result['count'] ?? 0;
    }
    
    // Count ambulances
    $ambulances_count = 0;
    $available_ambulances_count = 0;
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'ambulances'")) {
        $result = db_get_row("SELECT COUNT(*) as count FROM ambulances");
        $ambulances_count = $result['count'] ?? 0;
        
        $result = db_get_row("SELECT COUNT(*) as count FROM ambulances WHERE status = 'available'");
        $available_ambulances_count = $result['count'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    // Set defaults if database queries fail
    $patient_count = 0;
    $doctor_count = 0;
    $appointment_count = 0;
    $staff_count = 0;
    $beds_count = 0;
    $available_beds_count = 0;
    $ambulances_count = 0;
    $available_ambulances_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AKIRA HOSPITAL</title>
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
        
        .stat-card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            transition: transform 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.doctors {
            border-top: 5px solid #0066cc;
            background: linear-gradient(to bottom, #ffffff, #f0f7ff);
        }
        
        .stat-card.patients {
            border-top: 5px solid #9900cc;
            background: linear-gradient(to bottom, #ffffff, #f8f0ff);
        }
        
        .stat-card.appointments {
            border-top: 5px solid #00aa80;
            background: linear-gradient(to bottom, #ffffff, #f0fff8);
        }
        
        .stat-card.staff {
            border-top: 5px solid #ff9900;
            background: linear-gradient(to bottom, #ffffff, #fff8f0);
        }
        
        .stat-card.beds {
            border-top: 5px solid #ff9900;
            background: linear-gradient(to bottom, #ffffff, #fff8f0);
        }
        
        .stat-card.ambulances {
            border-top: 5px solid #ff3366;
            background: linear-gradient(to bottom, #ffffff, #fff0f5);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
            position: absolute;
            right: 20px;
            top: 20px;
        }
        
        .stat-doctors-icon {
            background-color: rgba(0, 102, 204, 0.1);
            color: #0066cc;
        }
        
        .stat-patients-icon {
            background-color: rgba(153, 0, 204, 0.1);
            color: #9900cc;
        }
        
        .stat-appointments-icon {
            background-color: rgba(0, 170, 128, 0.1);
            color: #00aa80;
        }
        
        .stat-staff-icon {
            background-color: rgba(255, 153, 0, 0.1);
            color: #ff9900;
        }
        
        .stat-beds-icon {
            background-color: rgba(255, 153, 0, 0.1);
            color: #ff9900;
        }
        
        .stat-ambulances-icon {
            background-color: rgba(255, 51, 102, 0.1);
            color: #ff3366;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            margin-top: 0.5rem;
            color: #333;
        }
        
        .stat-label {
            color: #6c757d;
            margin-bottom: 0;
            font-size: 1.1rem;
        }
        
        .stat-subtitle {
            font-size: 0.85rem;
            color: #777;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .stat-subtitle i {
            margin-right: 5px;
            color: #777;
        }
        
        .view-all-link {
            display: inline-block;
            margin-top: 1rem;
            color: #555;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .view-all-link i {
            margin-left: 5px;
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
        
        /* New KPI Widget Styles */
        .icon-bg {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 70px;
            min-height: 70px;
            background-color: rgba(240, 240, 240, 0.5);
        }
        
        .border-3 {
            border-width: 3px !important;
        }
        
        .border-start.border-primary {
            border-left-color: #4e73df !important;
        }
        
        .border-start.border-success {
            border-left-color: #1cc88a !important;
        }
        
        .border-start.border-danger {
            border-left-color: #e74a3b !important;
        }
        
        .border-start.border-info {
            border-left-color: #36b9cc !important;
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <h4 class="mb-0">Dashboard</h4>
                    </div>
                    <div class="user-welcome">
                        Welcome, <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span> (<?php echo htmlspecialchars($admin_role); ?>)
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="stat-card doctors">
                            <div class="stat-icon stat-doctors-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <p class="stat-label">Doctors</p>
                            <p class="stat-number"><?php echo number_format($doctor_count); ?></p>
                            <p class="stat-subtitle"><i class="fas fa-user-plus"></i> Medical Staff</p>
                            <a href="doctors.php" class="view-all-link">View All Doctors <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="stat-card patients">
                            <div class="stat-icon stat-patients-icon">
                                <i class="fas fa-user-injured"></i>
                            </div>
                            <p class="stat-label">Patients</p>
                            <p class="stat-number"><?php echo number_format($patient_count); ?></p>
                            <p class="stat-subtitle"><i class="fas fa-heartbeat"></i> Patient Care</p>
                            <a href="patients.php" class="view-all-link">View All Patients <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="stat-card appointments">
                            <div class="stat-icon stat-appointments-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <p class="stat-label">Today's Appointments</p>
                            <p class="stat-number"><?php echo number_format($appointment_count); ?></p>
                            <p class="stat-subtitle"><i class="fas fa-clock"></i> <?php echo date('l, M j'); ?></p>
                            <a href="appointments.php" class="view-all-link">View All Appointments <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Sections -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="section-title">Hospital Overview</h5>
                                
                                <div class="row g-4 mt-2">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center p-3 border-start border-primary border-3 bg-white rounded shadow-sm">
                                            <div class="icon-bg rounded-circle bg-light p-3 me-3">
                                                <i class="fas fa-bed text-primary fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Bed Occupancy</h6>
                                                <div class="d-flex align-items-baseline">
                                                    <h3 class="mb-0 me-2">225</h3>
                                                    <span class="text-muted">of 300 beds occupied</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center p-3 border-start border-success border-3 bg-white rounded shadow-sm">
                                            <div class="icon-bg rounded-circle bg-light p-3 me-3">
                                                <i class="fas fa-user-md text-success fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Staff Attendance</h6>
                                                <div class="d-flex align-items-baseline">
                                                    <h3 class="mb-0 me-2">46</h3>
                                                    <span class="text-muted">of 50 staff present today</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center p-3 border-start border-danger border-3 bg-white rounded shadow-sm">
                                            <div class="icon-bg rounded-circle bg-light p-3 me-3">
                                                <i class="fas fa-ambulance text-danger fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Ambulance Status</h6>
                                                <div class="d-flex align-items-baseline">
                                                    <h3 class="mb-0 me-2">6</h3>
                                                    <span class="text-muted">of 10 ambulances available</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center p-3 border-start border-info border-3 bg-white rounded shadow-sm">
                                            <div class="icon-bg rounded-circle bg-light p-3 me-3">
                                                <i class="fas fa-calendar-check text-info fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Today's Schedule</h6>
                                                <div class="d-flex align-items-baseline">
                                                    <h3 class="mb-0 me-2">15</h3>
                                                    <span class="text-muted">appointments (8 completed, 7 pending)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="section-title">Quick Links</h5>
                                
                                <div class="list-group">
                                    <a href="appointments.php?action=new" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-calendar-plus me-3 text-primary"></i>
                                        <span>New Appointment</span>
                                    </a>
                                    <a href="patients.php?action=new" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-user-plus me-3 text-success"></i>
                                        <span>Register Patient</span>
                                    </a>
                                    <a href="pharmacy.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-pills me-3 text-warning"></i>
                                        <span>Pharmacy Inventory</span>
                                    </a>
                                    <a href="laboratory.php?action=results" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-file-medical me-3 text-info"></i>
                                        <span>Lab Results</span>
                                    </a>
                                    <a href="billing.php?action=new" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <i class="fas fa-file-invoice me-3 text-danger"></i>
                                        <span>Generate Invoice</span>
                                    </a>
                                </div>
                                
                                <h5 class="section-title mt-4">System Status</h5>
                                <div class="p-3 bg-light rounded">
                                    <p class="mb-2"><i class="fas fa-database me-2 text-success"></i> Database: <span class="badge bg-success">Connected</span></p>
                                    <p class="mb-2"><i class="fas fa-server me-2 text-success"></i> Server: <span class="badge bg-success">Online</span></p>
                                    <p class="mb-0"><i class="fas fa-clock me-2 text-success"></i> Last Update: <span class="text-muted"><?php echo date('M d, Y H:i'); ?></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Comprehensive Footer -->
    <footer class="footer mt-auto pt-4 pb-3 bg-light">
        <div class="container-fluid">
            <div class="row gy-4">
                <div class="col-lg-3 col-md-6">
                    <h5 class="text-primary mb-3">AKIRA HOSPITAL</h5>
                    <p class="text-muted small">Providing top-quality healthcare services with compassion and excellence since 1995.</p>
                    <div class="d-flex gap-2 mt-3">
                        <a href="#" class="btn btn-sm btn-outline-primary rounded-circle" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="btn btn-sm btn-outline-primary rounded-circle" aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="btn btn-sm btn-outline-primary rounded-circle" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h5 class="text-primary mb-3">Contact Information</h5>
                    <ul class="list-unstyled text-muted small">
                        <li class="mb-2">
                            <i class="fas fa-map-marker-alt me-2"></i> 33, Ganesh  Street,  Vedasanthur, Dindigul District - 624710
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone me-2"></i> +1 (555) 123-4567
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-envelope me-2"></i> info@akirahospital.com
                        </li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h5 class="text-primary mb-3">Working Hours</h5>
                    <ul class="list-unstyled text-muted small">
                        <li class="mb-2">
                            <i class="far fa-clock me-2"></i> Monday - Friday: 8:00 AM - 8:00 PM
                        </li>
                        <li class="mb-2">
                            <i class="far fa-clock me-2"></i> Saturday: 8:00 AM - 6:00 PM
                        </li>
                        <li class="mb-2">
                            <i class="far fa-clock me-2"></i> Sunday: 9:00 AM - 1:00 PM
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-ambulance me-2"></i> Emergency Care: <span class="text-danger fw-bold">24/7</span>
                        </li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h5 class="text-primary mb-3">Quick Links</h5>
                    <ul class="list-unstyled small">
                        <li class="mb-2">
                            <a href="dashboard.php" class="text-decoration-none text-muted">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="appointments.php" class="text-decoration-none text-muted">
                                <i class="fas fa-calendar-check me-2"></i> Appointments
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="patients.php" class="text-decoration-none text-muted">
                                <i class="fas fa-user-injured me-2"></i> Patients
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="doctors.php" class="text-decoration-none text-muted">
                                <i class="fas fa-user-md me-2"></i> Doctors
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="pharmacy.php" class="text-decoration-none text-muted">
                                <i class="fas fa-pills me-2"></i> Pharmacy
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="billing.php" class="text-decoration-none text-muted">
                                <i class="fas fa-file-invoice-dollar me-2"></i> Billing
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="row mt-4 pt-3 border-top">
                <div class="col-md-6 text-center text-md-start small text-muted">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> AKIRA HOSPITAL Management System. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end small text-muted">
                    <p class="mb-0">Made with <i class="fas fa-heart text-danger"></i> for better healthcare</p>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>