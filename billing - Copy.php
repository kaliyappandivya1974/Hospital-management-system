<?php
/**
 * AKIRA HOSPITAL Management System
 * Billing and Invoices Management Page
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection and XAMPP compatibility
require_once 'db_connect.php';
require_once 'xampp_sync.php'; // Include XAMPP compatibility helper

// Check and fix database structure
try {
    // First check if the invoices table exists
    $table_exists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'invoices'")->fetchColumn();
    
    if ($table_exists) {
        // Check if due_date column exists
        $check_column = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'due_date'");
        if ($check_column && $check_column->rowCount() === 0) {
            // Add due_date column if it doesn't exist
            $pdo->exec("ALTER TABLE invoices ADD COLUMN due_date DATE NULL AFTER invoice_date");
            error_log("Added due_date column to invoices table");
        }
    } else {
        // Create the invoices table with all required columns
        $pdo->exec("
            CREATE TABLE invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                generated_by INT NOT NULL,
                invoice_date DATE NOT NULL,
                due_date DATE NULL,
                total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
                discount DECIMAL(10, 2) DEFAULT 0,
                tax DECIMAL(10, 2) DEFAULT 0,
                grand_total DECIMAL(10, 2) NOT NULL DEFAULT 0,
                payment_status ENUM('pending', 'partially_paid', 'paid') DEFAULT 'pending',
                payment_method VARCHAR(50) NULL,
                payment_date DATE NULL,
                paid_amount DECIMAL(10, 2) DEFAULT 0,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
                FOREIGN KEY (generated_by) REFERENCES admins(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        error_log("Created invoices table with all columns");
    }
} catch (PDOException $e) {
    error_log("Error checking/fixing database structure: " . $e->getMessage());
}

// Get admin information
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// Check if action is specified (new, edit, view, delete)
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// Create invoices table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'invoices'")) {
        db_query("
            CREATE TABLE IF NOT EXISTS invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                generated_by INT NOT NULL,
                invoice_date DATE NOT NULL,
                due_date DATE NULL,
                total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
                discount DECIMAL(10, 2) DEFAULT 0,
                tax DECIMAL(10, 2) DEFAULT 0,
                grand_total DECIMAL(10, 2) NOT NULL DEFAULT 0,
                payment_status ENUM('pending', 'partially_paid', 'paid') DEFAULT 'pending',
                payment_method VARCHAR(50) NULL,
                payment_date DATE NULL,
                paid_amount DECIMAL(10, 2) DEFAULT 0,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
                FOREIGN KEY (generated_by) REFERENCES admins(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        error_log("Created invoices table");
    }
    
    // Create invoice_items table if it doesn't exist
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'invoice_items'")) {
        db_query("
            CREATE TABLE IF NOT EXISTS invoice_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT NOT NULL,
                item_type VARCHAR(50) NOT NULL,
                item_id INT NULL,
                description TEXT NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(10, 2) NOT NULL,
                total_price DECIMAL(10, 2) NOT NULL,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        error_log("Created invoice_items table");
    }
} catch (PDOException $e) {
    error_log("Error creating invoices tables: " . $e->getMessage());
}

// Get list of invoices
$invoices = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'invoices'")) {
        $query = "SELECT i.*, p.name as patient_name, p.phone as patient_phone 
                FROM invoices i 
                JOIN patients p ON i.patient_id = p.id";
                
        if ($patient_id > 0) {
            $query .= " WHERE i.patient_id = :patient_id";
            $listStmt = $pdo->prepare($query);
            $listStmt->execute([':patient_id' => $patient_id]);
            $invoices = $listStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $listStmt = $pdo->prepare($query);
            $listStmt->execute();
            $invoices = $listStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching invoices: " . $e->getMessage());
}

// Get patients for dropdown
$patients = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'patients'")) {
        $patientsStmt = $pdo->prepare("SELECT id, name FROM patients ORDER BY name ASC");
        $patientsStmt->execute();
        $patients = $patientsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
}

// Get services for invoice
$available_services = [];
try {
    // Get lab tests as services
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'lab_tests'")) {
        $lab_tests = db_get_rows("SELECT id, name, price FROM lab_tests ORDER BY name ASC");
        foreach ($lab_tests as $test) {
            $available_services[] = [
                'id' => $test['id'],
                'type' => 'lab_test',
                'name' => $test['name'],
                'price' => $test['price']
            ];
        }
    }
    
    // Get medicines as services
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'medicines'")) {
        $medicines = db_get_rows("SELECT id, name, price FROM medicines ORDER BY name ASC");
        foreach ($medicines as $medicine) {
            $available_services[] = [
                'id' => $medicine['id'],
                'type' => 'medicine',
                'name' => $medicine['name'],
                'price' => $medicine['price']
            ];
        }
    }
    
    // Add common hospital services
    $common_services = [
        ['name' => 'Doctor Consultation', 'price' => 500.00],
        ['name' => 'Room Charges (General Ward) - Per Day', 'price' => 1000.00],
        ['name' => 'Room Charges (Semi-Private) - Per Day', 'price' => 2500.00],
        ['name' => 'Room Charges (Private) - Per Day', 'price' => 5000.00],
        ['name' => 'ICU Charges - Per Day', 'price' => 10000.00],
        ['name' => 'Emergency Room Fee', 'price' => 1500.00],
        ['name' => 'Ambulance Service - Local', 'price' => 800.00],
        ['name' => 'Nursing Care - Per Day', 'price' => 500.00],
        ['name' => 'Oxygen Charges - Per Hour', 'price' => 200.00],
        ['name' => 'Registration Fee', 'price' => 200.00]
    ];
    
    foreach ($common_services as $service) {
        $available_services[] = [
            'id' => null,
            'type' => 'service',
            'name' => $service['name'],
            'price' => $service['price']
        ];
    }
    
} catch (PDOException $e) {
    error_log("Error fetching services: " . $e->getMessage());
}

// Get invoice data and items
$invoice = null;
$invoice_items = [];
if (($action === 'view' || $action === 'edit' || $action === 'print') && $invoice_id > 0) {
    try {
        $query = "SELECT i.*, p.name as patient_name, p.phone as patient_phone, p.email as patient_email, p.address as patient_address 
                FROM invoices i 
                JOIN patients p ON i.patient_id = p.id
                WHERE i.id = :id";
        $invoiceStmt = $pdo->prepare($query);
        $invoiceStmt->execute([':id' => $invoice_id]);
        $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invoice) {
            $query = "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id";
            $itemsStmt = $pdo->prepare($query);
            $itemsStmt->execute([':invoice_id' => $invoice_id]);
            $invoice_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "Invoice not found";
            $action = 'list'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'list'; // Fallback to list
    }
}

// Handle form submission for new invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice'])) {
    try {
        // Get form data
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $discount_amount = floatval($_POST['discount_amount'] ?? 0);
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        
        // Get items data
        $item_types = $_POST['item_type'] ?? [];
        $item_ids = $_POST['item_id'] ?? [];
        $descriptions = $_POST['description'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $unit_prices = $_POST['unit_price'] ?? [];
        $total_prices = $_POST['total_price'] ?? [];
        
        // Calculate total amount
        $total_amount = array_sum($total_prices);
        
        // Add tax and subtract discount
        $total_amount = $total_amount + $tax_amount - $discount_amount;
        
        // Validation
        if ($patient_id <= 0) {
            $error = "Patient is required";
        } elseif (empty($item_types)) {
            $error = "At least one item is required for the invoice";
        } else {
            // Begin transaction
            $pdo->beginTransaction();
            
            if ($action === 'edit' && $invoice_id > 0) {
                // Update invoice
                $sql = "UPDATE invoices SET 
                        patient_id = :patient_id, 
                        invoice_date = :invoice_date, 
                        due_date = :due_date, 
                        total_amount = :total_amount, 
                        discount = :discount, 
                        tax = :tax,
                        grand_total = :grand_total,
                        notes = :notes 
                        WHERE id = :id";
                        
                // Calculate grand_total properly
                $grand_total = $total_amount + $tax_amount - $discount_amount;
                        
                $params = [
                    ':patient_id' => $patient_id,
                    ':invoice_date' => $invoice_date,
                    ':due_date' => $due_date,
                    ':total_amount' => $total_amount,
                    ':discount' => $discount_amount,
                    ':tax' => $tax_amount,
                    ':grand_total' => $grand_total,
                    ':notes' => $notes,
                    ':id' => $invoice_id
                ];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Delete existing items
                $deleteStmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = :invoice_id");
                $deleteStmt->execute([':invoice_id' => $invoice_id]);
                
                // Insert new items
                for ($i = 0; $i < count($item_types); $i++) {
                    $sql = "INSERT INTO invoice_items (invoice_id, item_type, item_id, description, quantity, unit_price, total_price) 
                            VALUES (:invoice_id, :item_type, :item_id, :description, :quantity, :unit_price, :total_price)";
                    $params = [
                        ':invoice_id' => $invoice_id,
                        ':item_type' => $item_types[$i],
                        ':item_id' => !empty($item_ids[$i]) ? $item_ids[$i] : null,
                        ':description' => $descriptions[$i],
                        ':quantity' => intval($quantities[$i]),
                        ':unit_price' => floatval($unit_prices[$i]),
                        ':total_price' => floatval($total_prices[$i])
                    ];
                    $itemStmt = $pdo->prepare($sql);
                    $itemStmt->execute($params);
                }
                
                $success = "Invoice updated successfully";
            } else {
                // Insert new invoice
                // Calculate grand total
                $grand_total = $total_amount;
                
                $sql = "INSERT INTO invoices (
                        patient_id, generated_by, invoice_date, total_amount, discount, tax, grand_total,
                        payment_status, due_date, paid_amount, notes, created_at
                    ) VALUES (
                        :patient_id, :generated_by, :invoice_date, :total_amount, :discount, :tax, :grand_total,
                        'pending', :due_date, 0, :notes, :created_at
                    )";
                $params = [
                    ':patient_id' => $patient_id,
                    ':generated_by' => $admin_id, // Using the logged-in admin
                    ':invoice_date' => $invoice_date,
                    ':due_date' => $due_date,
                    ':total_amount' => $total_amount,
                    ':discount' => $discount_amount,
                    ':tax' => $tax_amount,
                    ':grand_total' => $grand_total,
                    ':notes' => $notes,
                    ':created_at' => date('Y-m-d H:i:s')
                ];
                // Use db_insert function which returns the inserted ID
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Get the new invoice ID
                $new_invoice_id = $pdo->lastInsertId();
                
                // Insert items
                for ($i = 0; $i < count($item_types); $i++) {
                    $sql = "INSERT INTO invoice_items (invoice_id, item_type, item_id, description, quantity, unit_price, total_price) 
                            VALUES (:invoice_id, :item_type, :item_id, :description, :quantity, :unit_price, :total_price)";
                    $params = [
                        ':invoice_id' => $new_invoice_id,
                        ':item_type' => $item_types[$i],
                        ':item_id' => !empty($item_ids[$i]) ? $item_ids[$i] : null,
                        ':description' => $descriptions[$i],
                        ':quantity' => intval($quantities[$i]),
                        ':unit_price' => floatval($unit_prices[$i]),
                        ':total_price' => floatval($total_prices[$i])
                    ];
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                
                $invoice_id = $new_invoice_id; // Set for redirect
                $success = "Invoice created successfully";
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to view invoice
            header("Location: billing.php?action=view&id={$invoice_id}");
            exit;
        }
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle form submission for recording payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    try {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? '';
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        
        // Get current invoice using direct PDO
        $invoiceStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id");
        $invoiceStmt->execute([':id' => $invoice_id]);
        $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            $error = "Invoice not found";
        } else {
            // Calculate new paid amount and determine payment status
            $new_paid_amount = $invoice['paid_amount'] + $paid_amount;
            $payment_status = 'partially_paid';
            
            if ($new_paid_amount >= $invoice['total_amount']) {
                $payment_status = 'paid';
                $new_paid_amount = $invoice['total_amount']; // Cap at total amount
            } elseif ($new_paid_amount <= 0) {
                $payment_status = 'pending';
            }
            
            // Update invoice
            $sql = "UPDATE invoices SET 
                    paid_amount = :paid_amount, 
                    payment_status = :payment_status, 
                    payment_method = :payment_method, 
                    payment_date = :payment_date 
                    WHERE id = :id";
            $params = [
                ':paid_amount' => $new_paid_amount,
                ':payment_status' => $payment_status,
                ':payment_method' => $payment_method,
                ':payment_date' => $payment_date,
                ':id' => $invoice_id
            ];
            $paymentStmt = $pdo->prepare($sql);
            $paymentStmt->execute($params);
            
            $success = "Payment recorded successfully";
            // Redirect to view invoice
            header("Location: billing.php?action=view&id={$invoice_id}");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle delete invoice action
if ($action === 'delete' && $invoice_id > 0) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete invoice items first
        $deleteItemsStmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = :id");
        $deleteItemsStmt->execute([':id' => $invoice_id]);
        
        // Delete invoice
        $deleteInvoiceStmt = $pdo->prepare("DELETE FROM invoices WHERE id = :id"); 
        $deleteInvoiceStmt->execute([':id' => $invoice_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $success = "Invoice deleted successfully";
        // Redirect to invoice list
        header("Location: billing.php");
        exit;
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Page title based on action
$page_title = "Billing";
if ($action === 'new') {
    $page_title = "Create New Invoice";
} elseif ($action === 'edit') {
    $page_title = "Edit Invoice";
} elseif ($action === 'view') {
    $page_title = "Invoice Details";
} elseif ($action === 'print') {
    $page_title = "Print Invoice";
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
        
        .invoice-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--primary-color);
        }
        
        .invoice-card:hover {
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
        
        .status-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-partially_paid {
            background-color: #17a2b8;
            color: white;
        }
        
        .status-paid {
            background-color: #28a745;
            color: white;
        }
        
        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }
        
        .price-tag {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        /* Print Invoice Styles */
        .invoice-print-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .invoice-print-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .invoice-print-header hr {
            margin: 1rem 0;
            border-top: 1px solid #ccc;
        }
        
        .invoice-meta {
            margin-bottom: 2rem;
        }
        
        .invoice-id {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .patient-info, .invoice-info {
            margin-bottom: 1rem;
        }
        
        .invoice-total-box {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .payment-status-box {
            margin-top: 1rem;
            padding: 1rem;
            background-color: #eef4ff;
            border-radius: 5px;
            border-left: 4px solid var(--primary-color);
        }
        
        @media print {
            body {
                background-color: white;
                margin: 0;
                padding: 0;
            }
            
            .sidebar, .topbar, .no-print, nav, footer {
                display: none !important;
            }
            
            .invoice-print-container {
                width: 100%;
                padding: 1rem;
            }
            
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            
            .card-body {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body class="<?php echo ($action === 'print') ? 'print-mode' : ''; ?>">
    <div class="container-fluid">
        <div class="row">
            <?php if ($action !== 'print'): ?>
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
                        <a class="nav-link active" href="billing.php">
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
            <?php else: ?>
            <!-- Print Mode - Full Width -->
            <div class="col-12 invoice-print-container">
            <?php endif; ?>
                    
                    <!-- Different content based on action -->
                    <?php if ($action === 'list'): ?>
                        <!-- Invoices List View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Invoices</h5>
                                <div>
                                    <a href="billing.php?action=new" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i> Create New Invoice
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Patient Filter -->
                                <?php if (!empty($patients)): ?>
                                <div class="mb-4">
                                    <form class="d-flex align-items-center" method="get">
                                        <label for="patientFilter" class="me-2">Filter by Patient:</label>
                                        <select id="patientFilter" name="patient_id" class="form-select form-select-sm me-2" style="max-width: 200px;">
                                            <option value="0">All Patients</option>
                                            <?php foreach ($patients as $patient): ?>
                                                <option value="<?php echo $patient['id']; ?>" <?php echo ($patient_id == $patient['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($patient['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (empty($invoices)): ?>
                                    <div class="alert alert-info">
                                        No invoices found.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Invoice #</th>
                                                    <th>Patient</th>
                                                    <th>Date</th>
                                                    <th>Total (₹)</th>
                                                    <th>Paid (₹)</th>
                                                    <th>Balance (₹)</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($invoices as $inv): ?>
                                                    <tr>
                                                        <td><strong># <?php echo $inv['id']; ?></strong></td>
                                                        <td><?php echo htmlspecialchars($inv['patient_name']); ?></td>
                                                        <td><?php echo date('d-m-Y', strtotime($inv['invoice_date'])); ?></td>
                                                        <td class="price-tag">₹<?php echo number_format($inv['total_amount'], 2); ?></td>
                                                        <td>₹<?php echo number_format($inv['paid_amount'] ?? 0, 2); ?></td>
                                                        <td>₹<?php echo number_format(($inv['total_amount'] ?? 0) - ($inv['paid_amount'] ?? 0), 2); ?></td>
                                                        <td>
                                                            <?php
                                                            $statusClass = 'status-pending';
                                                            switch ($inv['payment_status']) {
                                                                case 'partially_paid':
                                                                    $statusClass = 'status-partially_paid';
                                                                    break;
                                                                case 'paid':
                                                                    $statusClass = 'status-paid';
                                                                    break;
                                                                case 'cancelled':
                                                                    $statusClass = 'status-cancelled';
                                                                    break;
                                                            }
                                                            
                                                            $status_label = str_replace('_', ' ', $inv['payment_status']);
                                                            ?>
                                                            <span class="badge <?php echo $statusClass; ?>"><?php echo ucwords($status_label); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="billing.php?action=view&id=<?php echo $inv['id']; ?>" class="btn btn-primary">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <?php if ($inv['payment_status'] !== 'paid' && $inv['payment_status'] !== 'cancelled'): ?>
                                                                <a href="billing.php?action=edit&id=<?php echo $inv['id']; ?>" class="btn btn-secondary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <?php endif; ?>
                                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $inv['id']; ?>)" class="btn btn-danger">
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
                        <!-- Create/Edit Invoice Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo $action === 'new' ? 'Create New Invoice' : 'Edit Invoice'; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="post" id="invoiceForm" action="<?php echo $action === 'edit' ? "billing.php?action=edit&id={$invoice_id}" : "billing.php?action=new"; ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                                            <select class="form-select" id="patient_id" name="patient_id" required<?php echo $action === 'edit' ? ' disabled' : ''; ?>>
                                                <option value="">Select Patient</option>
                                                <?php foreach ($patients as $patient): ?>
                                                    <option value="<?php echo $patient['id']; ?>" <?php echo (isset($invoice['patient_id']) && $invoice['patient_id'] == $patient['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($patient['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($action === 'edit'): ?>
                                                <input type="hidden" name="patient_id" value="<?php echo $invoice['patient_id']; ?>">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="invoice_date" class="form-label">Invoice Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?php echo $invoice['invoice_date'] ?? date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="due_date" class="form-label">Due Date</label>
                                            <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo $invoice['due_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <h6 class="mt-4 mb-3">Invoice Items</h6>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="invoiceItemsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="40%">Description</th>
                                                    <th width="15%">Quantity</th>
                                                    <th width="20%">Unit Price (₹)</th>
                                                    <th width="20%">Total (₹)</th>
                                                    <th width="5%"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="invoiceItemsBody">
                                                <?php if ($action === 'edit' && !empty($invoice_items)): ?>
                                                    <?php foreach ($invoice_items as $index => $item): ?>
                                                        <tr>
                                                            <td>
                                                                <input type="hidden" name="item_type[]" value="<?php echo htmlspecialchars($item['item_type']); ?>">
                                                                <input type="hidden" name="item_id[]" value="<?php echo $item['item_id']; ?>">
                                                                <input type="text" class="form-control" name="description[]" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                                                            </td>
                                                            <td>
                                                                <input type="number" class="form-control quantity-input" name="quantity[]" value="<?php echo $item['quantity']; ?>" min="1" required>
                                                            </td>
                                                            <td>
                                                                <input type="number" step="0.01" min="0" class="form-control price-input" name="unit_price[]" value="<?php echo $item['unit_price']; ?>" required>
                                                            </td>
                                                            <td>
                                                                <input type="number" step="0.01" min="0" class="form-control total-input" name="total_price[]" value="<?php echo $item['total_price']; ?>" readonly>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-danger remove-item"><i class="fas fa-times"></i></button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td>
                                                            <input type="hidden" name="item_type[]" value="service">
                                                            <input type="hidden" name="item_id[]" value="">
                                                            <input type="text" class="form-control" name="description[]" placeholder="Enter description" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control quantity-input" name="quantity[]" value="1" min="1" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" min="0" class="form-control price-input" name="unit_price[]" value="0.00" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" min="0" class="form-control total-input" name="total_price[]" value="0.00" readonly>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-danger remove-item"><i class="fas fa-times"></i></button>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5">
                                                        <button type="button" class="btn btn-sm btn-success" id="addItemBtn">
                                                            <i class="fas fa-plus me-1"></i> Add Item
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-info ms-2" id="addServiceBtn">
                                                            <i class="fas fa-list me-1"></i> Add from Services
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    
                                    <div class="row mt-4">
                                        <div class="col-md-6 mb-3">
                                            <label for="notes" class="form-label">Notes</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo $invoice['notes'] ?? ''; ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6 class="card-title">Invoice Summary</h6>
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span>Subtotal:</span>
                                                        <span class="price-tag" id="subtotal">₹0.00</span>
                                                    </div>
                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <label for="tax_amount">Tax:</label>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="number" step="0.01" class="form-control" id="tax_amount" name="tax_amount" value="<?php echo ($action === 'edit') ? $invoice['tax'] : '0.00'; ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <label for="discount_amount">Discount:</label>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="number" step="0.01" class="form-control" id="discount_amount" name="discount_amount" value="<?php echo ($action === 'edit') ? $invoice['discount'] : '0.00'; ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                    <div class="d-flex justify-content-between">
                                                        <strong>Total:</strong>
                                                        <span class="price-tag h5" id="grandTotal">₹0.00</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="billing.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="save_invoice" class="btn btn-primary">Save Invoice</button>
                                    </div>
                                </form>
                                
                                <!-- Services Modal -->
                                <div class="modal fade" id="servicesModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Add Service</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="table-responsive">
                                                    <table class="table">
                                                        <thead>
                                                            <tr>
                                                                <th>Service Name</th>
                                                                <th>Type</th>
                                                                <th>Price (₹)</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($available_services as $service): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($service['name']); ?></td>
                                                                <td><?php echo ucfirst(str_replace('_', ' ', $service['type'] ?? 'service')); ?></td>
                                                                <td>₹<?php echo number_format($service['price'], 2); ?></td>
                                                                <td>
                                                                    <button type="button" 
                                                                            class="btn btn-sm btn-primary add-service-btn"
                                                                            data-type="<?php echo $service['type'] ?? 'service'; ?>"
                                                                            data-id="<?php echo $service['id'] ?? ''; ?>"
                                                                            data-name="<?php echo htmlspecialchars($service['name']); ?>"
                                                                            data-price="<?php echo $service['price']; ?>">
                                                                        <i class="fas fa-plus"></i> Add
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($action === 'view' || $action === 'print'): ?>
                        <!-- View/Print Invoice -->
                        <?php if ($invoice): ?>
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <?php if ($action === 'print'): ?>
                                        <div class="invoice-print-header">
                                            <h1 class="invoice-print-title">AKIRA HOSPITAL</h1>
                                            <p>123 Health Avenue, Medical District, City - 110001</p>
                                            <p>Phone: +91 11 2345 6789 | Email: info@akirahospital.com</p>
                                            <hr>
                                            <h2>INVOICE</h2>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex justify-content-between mb-4">
                                            <h5 class="card-title">Invoice #<?php echo $invoice['id']; ?></h5>
                                            <div>
                                                <a href="billing.php?action=print&id=<?php echo $invoice['id']; ?>" class="btn btn-outline-secondary btn-sm me-2" target="_blank">
                                                    <i class="fas fa-print me-1"></i> Print
                                                </a>
                                                <?php if ($invoice['payment_status'] !== 'paid' && $invoice['payment_status'] !== 'cancelled'): ?>
                                                <a href="billing.php?action=edit&id=<?php echo $invoice['id']; ?>" class="btn btn-outline-primary btn-sm me-2">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                                    <i class="fas fa-money-bill-wave me-1"></i> Record Payment
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row invoice-meta">
                                        <div class="col-md-6">
                                            <div class="patient-info">
                                                <h6>Bill To:</h6>
                                                <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['patient_name']); ?></strong></p>
                                                <?php if (!empty($invoice['patient_address'])): ?>
                                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($invoice['patient_address'])); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($invoice['patient_phone'])): ?>
                                                    <p class="mb-1">Phone: <?php echo htmlspecialchars($invoice['patient_phone']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($invoice['patient_email'])): ?>
                                                    <p>Email: <?php echo htmlspecialchars($invoice['patient_email']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="invoice-info">
                                                <h6>Invoice Details:</h6>
                                                <p class="mb-1"><span class="invoice-id">Invoice #<?php echo $invoice['id']; ?></span></p>
                                                <p class="mb-1"><strong>Date:</strong> <?php echo date('d-m-Y', strtotime($invoice['invoice_date'])); ?></p>
                                                <?php if (!empty($invoice['due_date'])): ?>
                                                    <p class="mb-1"><strong>Due Date:</strong> <?php echo date('d-m-Y', strtotime($invoice['due_date'])); ?></p>
                                                <?php endif; ?>
                                                <p>
                                                    <strong>Status:</strong> 
                                                    <?php
                                                    $statusClass = 'status-pending';
                                                    switch ($invoice['payment_status']) {
                                                        case 'partially_paid':
                                                            $statusClass = 'status-partially_paid';
                                                            break;
                                                        case 'paid':
                                                            $statusClass = 'status-paid';
                                                            break;
                                                        case 'cancelled':
                                                            $statusClass = 'status-cancelled';
                                                            break;
                                                    }
                                                    
                                                    $status_label = str_replace('_', ' ', $invoice['payment_status']);
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucwords($status_label); ?></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive mb-4">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="5%">#</th>
                                                    <th width="50%">Description</th>
                                                    <th width="10%">Qty</th>
                                                    <th width="15%">Unit Price</th>
                                                    <th width="20%">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($invoice_items)): ?>
                                                    <?php foreach ($invoice_items as $index => $item): ?>
                                                        <tr>
                                                            <td><?php echo $index + 1; ?></td>
                                                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                            <td><?php echo $item['quantity']; ?></td>
                                                            <td>₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                                            <td>₹<?php echo number_format($item['total_price'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center">No items</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="4" class="text-end">Subtotal:</th>
                                                    <td>₹<?php 
                                                        $subtotal = array_sum(array_column($invoice_items, 'total_price'));
                                                        echo number_format($subtotal, 2); 
                                                    ?></td>
                                                </tr>
                                                <?php if ($invoice['tax'] > 0): ?>
                                                <tr>
                                                    <th colspan="4" class="text-end">Tax:</th>
                                                    <td>₹<?php echo number_format($invoice['tax'], 2); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if ($invoice['discount'] > 0): ?>
                                                <tr>
                                                    <th colspan="4" class="text-end">Discount:</th>
                                                    <td>- ₹<?php echo number_format($invoice['discount'], 2); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <th colspan="4" class="text-end">Total Amount:</th>
                                                    <td class="price-tag">₹<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    
                                    <?php if (!empty($invoice['notes'])): ?>
                                    <div class="mb-4">
                                        <h6>Notes:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="payment-status-box">
                                        <h6>Payment Information</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <p class="mb-1"><strong>Total Amount:</strong> ₹<?php echo number_format($invoice['total_amount'], 2); ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p class="mb-1"><strong>Paid Amount:</strong> ₹<?php echo number_format($invoice['paid_amount'], 2); ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p class="mb-1"><strong>Due Amount:</strong> ₹<?php echo number_format($invoice['total_amount'] - $invoice['paid_amount'], 2); ?></p>
                                            </div>
                                        </div>
                                        <?php if ($invoice['payment_status'] === 'paid' || $invoice['payment_status'] === 'partially_paid'): ?>
                                        <div class="mt-2">
                                            <p class="mb-1"><strong>Payment Method:</strong> <?php echo htmlspecialchars($invoice['payment_method'] ?? 'N/A'); ?></p>
                                            <?php if (!empty($invoice['payment_date'])): ?>
                                                <p class="mb-0"><strong>Payment Date:</strong> <?php echo date('d-m-Y', strtotime($invoice['payment_date'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($action === 'print'): ?>
                                    <div class="mt-5 text-center">
                                        <p>This is a computer-generated invoice and does not require a signature.</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="mt-4 no-print">
                                        <a href="billing.php" class="btn btn-secondary">Back to Invoices</a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($action === 'view'): ?>
                            <!-- Payment Modal -->
                            <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Record Payment</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="post" action="billing.php?action=view&id=<?php echo $invoice['id']; ?>">
                                            <div class="modal-body">
                                                <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <p><strong>Invoice #<?php echo $invoice['id']; ?></strong> for <?php echo htmlspecialchars($invoice['patient_name']); ?></p>
                                                    <p class="mb-1"><strong>Total Amount:</strong> ₹<?php echo number_format($invoice['total_amount'], 2); ?></p>
                                                    <p class="mb-1"><strong>Amount Paid:</strong> ₹<?php echo number_format($invoice['paid_amount'], 2); ?></p>
                                                    <p><strong>Balance Due:</strong> ₹<?php echo number_format($invoice['total_amount'] - $invoice['paid_amount'], 2); ?></p>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="paid_amount" class="form-label">Payment Amount (₹) <span class="text-danger">*</span></label>
                                                    <input type="number" step="0.01" min="0" max="<?php echo $invoice['total_amount'] - $invoice['paid_amount']; ?>" class="form-control" id="paid_amount" name="paid_amount" value="<?php echo $invoice['total_amount'] - $invoice['paid_amount']; ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                                        <option value="">Select Payment Method</option>
                                                        <option value="Cash">Cash</option>
                                                        <option value="Credit Card">Credit Card</option>
                                                        <option value="Debit Card">Debit Card</option>
                                                        <option value="UPI">UPI</option>
                                                        <option value="Bank Transfer">Bank Transfer</option>
                                                        <option value="Insurance">Insurance</option>
                                                        <option value="Cheque">Cheque</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="record_payment" class="btn btn-primary">Record Payment</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                Invoice not found.
                            </div>
                            <a href="billing.php" class="btn btn-secondary">Back to Invoices</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($action !== 'print'): ?>
    <!-- JavaScript for confirmation dialogs and calculations -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap modal
            const servicesModal = new bootstrap.Modal(document.getElementById('servicesModal'));
            
            // Add from Services button click handler
            const addServiceBtn = document.getElementById('addServiceBtn');
            if (addServiceBtn) {
                addServiceBtn.addEventListener('click', function() {
                    servicesModal.show();
                });
            }
            
            // Add service from modal
            const addServiceBtns = document.querySelectorAll('.add-service-btn');
            addServiceBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const price = parseFloat(this.getAttribute('data-price'));
                    
                    addNewRow(type, id, name, 1, price);
                    servicesModal.hide();
                });
            });
            
            // Function to add a new row
            function addNewRow(itemType, itemId, description, quantity, price) {
                const invoiceItemsBody = document.getElementById('invoiceItemsBody');
                if (!invoiceItemsBody) return;
                
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                    <td>
                        <input type="hidden" name="item_type[]" value="${itemType}">
                        <input type="hidden" name="item_id[]" value="${itemId}">
                        <input type="text" class="form-control" name="description[]" value="${description}" required>
                    </td>
                    <td>
                        <input type="number" class="form-control quantity-input" name="quantity[]" value="${quantity}" min="1" required>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" class="form-control price-input" name="unit_price[]" value="${price.toFixed(2)}" required>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" class="form-control total-input" name="total_price[]" value="${(quantity * price).toFixed(2)}" readonly>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger remove-item"><i class="fas fa-times"></i></button>
                    </td>
                `;
                invoiceItemsBody.appendChild(newRow);
                attachEventListeners();
                updateTotals();
            }
            
            // Add item button click handler
            const addItemBtn = document.getElementById('addItemBtn');
            if (addItemBtn) {
                addItemBtn.addEventListener('click', function() {
                    addNewRow('service', '', '', 1, 0);
                });
            }
            
            // Remove item row
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item') || e.target.parentElement.classList.contains('remove-item')) {
                    const row = e.target.closest('tr');
                    const invoiceItemsBody = document.getElementById('invoiceItemsBody');
                    if (invoiceItemsBody && invoiceItemsBody.children.length > 1) {
                        row.remove();
                        updateTotals();
                    } else {
                        alert('At least one item is required.');
                    }
                }
            });
            
            // Update row total when quantity or price changes
            function attachEventListeners() {
                const quantityInputs = document.querySelectorAll('.quantity-input');
                const priceInputs = document.querySelectorAll('.price-input');
                
                quantityInputs.forEach(input => {
                    input.addEventListener('input', function() {
                        updateRowTotal(this);
                        updateTotals();
                    });
                });
                
                priceInputs.forEach(input => {
                    input.addEventListener('input', function() {
                        updateRowTotal(this);
                        updateTotals();
                    });
                });
            }
            
            function updateRowTotal(input) {
                const row = input.closest('tr');
                const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const total = quantity * price;
                row.querySelector('.total-input').value = total.toFixed(2);
            }
            
            // Update invoice totals
            function updateTotals() {
                const totalInputs = document.querySelectorAll('.total-input');
                const taxAmountInput = document.getElementById('tax_amount');
                const discountAmountInput = document.getElementById('discount_amount');
                const subtotalDisplay = document.getElementById('subtotal');
                const grandTotalDisplay = document.getElementById('grandTotal');
                
                let subtotal = 0;
                totalInputs.forEach(input => {
                    subtotal += parseFloat(input.value) || 0;
                });
                
                const taxAmount = parseFloat(taxAmountInput?.value) || 0;
                const discountAmount = parseFloat(discountAmountInput?.value) || 0;
                const grandTotal = subtotal + taxAmount - discountAmount;
                
                if (subtotalDisplay) subtotalDisplay.textContent = '₹' + subtotal.toFixed(2);
                if (grandTotalDisplay) grandTotalDisplay.textContent = '₹' + grandTotal.toFixed(2);
            }
            
            // Initialize calculations
            attachEventListeners();
            updateTotals();
            
            // Update totals when tax or discount changes
            const taxAmountInput = document.getElementById('tax_amount');
            const discountAmountInput = document.getElementById('discount_amount');
            
            if (taxAmountInput) {
                taxAmountInput.addEventListener('input', updateTotals);
            }
            if (discountAmountInput) {
                discountAmountInput.addEventListener('input', updateTotals);
            }
        });
        
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
                window.location.href = 'billing.php?action=delete&id=' + id;
            }
        }
        
        <?php if ($action === 'print'): ?>
        // Auto print when in print mode
        window.onload = function() {
            window.print();
        }
        <?php endif; ?>
    </script>
    <?php endif; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>