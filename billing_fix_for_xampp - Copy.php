<?php
/**
 * AKIRA HOSPITAL Management System
 * Billing and Invoices Management Page with XAMPP Support
 * Fixed version for cross-database compatibility
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

// Get admin information
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// Check if action is specified (new, edit, view, delete)
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

// Auto-create from appointment
$appointment_data = null;
if ($appointment_id > 0) {
    // Get appointment details for auto-creating invoice
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, p.name as patient_name, p.id as patient_id, 
                   d.name as doctor_name, d.fee as doctor_fee
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $appointment_id]);
        $appointment_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($appointment_data) {
            $patient_id = $appointment_data['patient_id'];
            $action = 'new'; // Force new invoice action
        }
    } catch (PDOException $e) {
        error_log("Error fetching appointment data: " . $e->getMessage());
    }
}

// Error and success messages
$error = isset($_GET['error']) ? $_GET['error'] : null;
$success = isset($_GET['success']) ? $_GET['success'] : null;

// Create invoices table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'invoices'")) {
        db_query("
            CREATE TABLE IF NOT EXISTS invoices (
                id SERIAL PRIMARY KEY,
                patient_id INT NOT NULL,
                generated_by INT NOT NULL,
                invoice_date DATE NOT NULL,
                total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
                discount DECIMAL(10, 2) DEFAULT 0,
                tax DECIMAL(10, 2) DEFAULT 0,
                grand_total DECIMAL(10, 2) NOT NULL DEFAULT 0,
                payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                payment_method VARCHAR(50) NULL,
                payment_date DATE NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
                FOREIGN KEY (generated_by) REFERENCES admins(id)
            )
        ");
        error_log("Created invoices table");
    }

    // Create invoice_items table if it doesn't exist
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'invoice_items'")) {
        db_query("
            CREATE TABLE IF NOT EXISTS invoice_items (
                id SERIAL PRIMARY KEY,
                invoice_id INT NOT NULL,
                item_type VARCHAR(50) NOT NULL,
                item_id INT NULL,
                description TEXT NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(10, 2) NOT NULL,
                total_price DECIMAL(10, 2) NOT NULL,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            )
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
            $params = [':patient_id' => $patient_id];
        } else {
            $params = [];
        }
        
        $query .= " ORDER BY i.invoice_date DESC";
        
        $listStmt = $pdo->prepare($query);
        $listStmt->execute($params);
        $invoices = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching invoices: " . $e->getMessage());
}

// Get patients for dropdown
$patients = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'patients'")) {
        $patientsStmt = $pdo->prepare("SELECT id, name, medical_record_number FROM patients ORDER BY name ASC");
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
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'lab_test_types'")) {
        $lab_tests = db_get_rows("SELECT id, name, price FROM lab_test_types ORDER BY name ASC");
        foreach ($lab_tests as $test) {
            $available_services[] = [
                'id' => $test['id'],
                'type' => 'lab_test',
                'name' => 'Lab: ' . $test['name'],
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
                'name' => 'Med: ' . $medicine['name'],
                'price' => $medicine['price']
            ];
        }
    }

    // Add common hospital services
    $common_services = [
        ['name' => 'Doctor Consultation', 'price' => 500.00],
        ['name' => 'Specialist Consultation', 'price' => 1000.00],
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
        $query = "SELECT i.*, p.name as patient_name, p.phone as patient_phone, p.email as patient_email, 
                         p.address as patient_address, p.medical_record_number 
                  FROM invoices i 
                  JOIN patients p ON i.patient_id = p.id
                  WHERE i.id = :id";
        $invoiceStmt = $pdo->prepare($query);
        $invoiceStmt->execute([':id' => $invoice_id]);
        $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);

        if ($invoice) {
            $query = "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id ASC";
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
        $total_amount = 0;
        foreach ($total_prices as $price) {
            $total_amount += floatval($price);
        }

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
                        discount_amount = :discount_amount, 
                        tax_amount = :tax_amount, 
                        notes = :notes 
                        WHERE id = :id";
                $params = [
                    ':patient_id' => $patient_id,
                    ':invoice_date' => $invoice_date,
                    ':due_date' => $due_date,
                    ':total_amount' => $total_amount,
                    ':discount_amount' => $discount_amount,
                    ':tax_amount' => $tax_amount,
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
                // Get the admin ID from session
                $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1;
                
                // Calculate grand total
                $grand_total = $total_amount;
                
                $sql = "INSERT INTO invoices (
                        patient_id, generated_by, invoice_date, total_amount, discount, tax, grand_total,
                        payment_status, due_date, paid_amount, discount_amount, tax_amount, notes, created_at
                    ) VALUES (
                        :patient_id, :generated_by, :invoice_date, :total_amount, :discount, :tax, :grand_total,
                        'pending', :due_date, 0, :discount_amount, :tax_amount, :notes, :created_at
                    )";
                $params = [
                    ':patient_id' => $patient_id,
                    ':generated_by' => $admin_id,
                    ':invoice_date' => $invoice_date,
                    ':due_date' => $due_date,
                    ':total_amount' => $total_amount,
                    ':discount' => $discount_amount,
                    ':tax' => $tax_amount,
                    ':grand_total' => $total_amount,
                    ':discount_amount' => $discount_amount,
                    ':tax_amount' => $tax_amount,
                    ':notes' => $notes,
                    ':created_at' => date('Y-m-d H:i:s')
                ];
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Get the new invoice ID
                $new_invoice_id = $pdo->lastInsertId();
                
                if (!$new_invoice_id) {
                    // Fallback for databases that don't support lastInsertId()
                    $seqStmt = $pdo->query("SELECT MAX(id) as last_id FROM invoices");
                    $seqRow = $seqStmt->fetch(PDO::FETCH_ASSOC);
                    $new_invoice_id = $seqRow['last_id'];
                }

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
                    $itemStmt = $pdo->prepare($sql);
                    $itemStmt->execute($params);
                }

                $invoice_id = $new_invoice_id; // Set for redirect
                $success = "Invoice created successfully";
            }

            // Commit transaction
            $pdo->commit();

            // Redirect to view invoice
            header("Location: billing_fix_for_xampp.php?action=view&id={$invoice_id}&success=" . urlencode($success));
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
            header("Location: billing_fix_for_xampp.php?action=view&id={$invoice_id}&success=" . urlencode($success));
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
        header("Location: billing_fix_for_xampp.php?success=" . urlencode($success));
        exit;
    } catch (PDOException $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Database error: " . $e->getMessage();
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
                <h1 class="h2">Billing Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <?php if ($action !== 'new' && $action !== 'edit'): ?>
                            <a href="billing_fix_for_xampp.php?action=new" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-plus"></i> Create New Invoice
                            </a>
                        <?php endif; ?>
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
            
            <?php if ($action === 'list'): ?>
                <!-- Invoices List -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <?php if (empty($invoices)): ?>
                            <div class="alert alert-info">
                                No invoices found. <a href="billing_fix_for_xampp.php?action=new" class="alert-link">Create your first invoice</a>.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Patient</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Paid</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $inv): ?>
                                            <tr>
                                                <td>INV-<?php echo str_pad($inv['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars($inv['patient_name']); ?></td>
                                                <td><?php echo date('d-M-Y', strtotime($inv['invoice_date'])); ?></td>
                                                <td>₹<?php echo number_format($inv['total_amount'], 2); ?></td>
                                                <td>₹<?php echo number_format($inv['paid_amount'], 2); ?></td>
                                                <td>
                                                    <?php if ($inv['payment_status'] === 'paid'): ?>
                                                        <span class="badge bg-success">Paid</span>
                                                    <?php elseif ($inv['payment_status'] === 'partially_paid'): ?>
                                                        <span class="badge bg-warning text-dark">Partially Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="billing_fix_for_xampp.php?action=view&id=<?php echo $inv['id']; ?>" class="btn btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="billing_fix_for_xampp.php?action=edit&id=<?php echo $inv['id']; ?>" class="btn btn-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $inv['id']; ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Delete Confirmation Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $inv['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete invoice <strong>INV-<?php echo str_pad($inv['id'], 6, '0', STR_PAD_LEFT); ?></strong> for <strong><?php echo htmlspecialchars($inv['patient_name']); ?></strong>?
                                                                    <p class="text-danger mt-2"><strong>Warning:</strong> This action cannot be undone.</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <a href="billing_fix_for_xampp.php?action=delete&id=<?php echo $inv['id']; ?>" class="btn btn-danger">Delete</a>
                                                                </div>
                                                            </div>
                                                        </div>
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
                <!-- New/Edit Invoice Form -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo ($action === 'new') ? 'Create New Invoice' : 'Edit Invoice'; ?></h5>
                        
                        <form action="billing_fix_for_xampp.php?action=<?php echo $action; ?><?php echo ($action === 'edit') ? '&id=' . $invoice_id : ''; ?>" method="post" id="invoiceForm">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                                    <select class="form-select" id="patient_id" name="patient_id" required>
                                        <option value="">-- Select Patient --</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>" <?php 
                                                if (($action === 'edit' && $invoice['patient_id'] == $patient['id']) || 
                                                    ($patient_id > 0 && $patient_id == $patient['id'])) {
                                                    echo 'selected';
                                                }
                                            ?>>
                                                <?php echo htmlspecialchars($patient['name']); ?> 
                                                <?php if (!empty($patient['medical_record_number'])): ?>
                                                    (MRN: <?php echo htmlspecialchars($patient['medical_record_number']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="invoice_date" class="form-label">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="invoice_date" name="invoice_date" required 
                                        value="<?php echo ($action === 'edit') ? $invoice['invoice_date'] : date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="due_date" class="form-label">Due Date</label>
                                    <input type="date" class="form-control" id="due_date" name="due_date" 
                                        value="<?php echo ($action === 'edit' && !empty($invoice['due_date'])) ? $invoice['due_date'] : date('Y-m-d', strtotime('+15 days')); ?>">
                                </div>
                            </div>
                            
                            <!-- Invoice Items Section -->
                            <h6 class="mt-4 mb-3">Invoice Items</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="invoice_items">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%;">Item Description</th>
                                            <th style="width: 15%;">Quantity</th>
                                            <th style="width: 20%;">Unit Price (₹)</th>
                                            <th style="width: 20%;">Total (₹)</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Pre-fill with appointment data if available
                                        if ($appointment_data && $action === 'new'): ?>
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="item_type[]" value="service">
                                                    <input type="hidden" name="item_id[]" value="">
                                                    <input type="text" class="form-control" name="description[]" 
                                                        value="Doctor Consultation - Dr. <?php echo htmlspecialchars($appointment_data['doctor_name']); ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control item-quantity" name="quantity[]" min="1" value="1" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control item-price" name="unit_price[]" 
                                                        value="<?php echo (!empty($appointment_data['doctor_fee'])) ? $appointment_data['doctor_fee'] : '500.00'; ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control item-total" name="total_price[]" 
                                                        value="<?php echo (!empty($appointment_data['doctor_fee'])) ? $appointment_data['doctor_fee'] : '500.00'; ?>" readonly>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button>
                                                </td>
                                            </tr>
                                        <?php elseif ($action === 'edit' && !empty($invoice_items)): ?>
                                            <?php foreach ($invoice_items as $item): ?>
                                                <tr>
                                                    <td>
                                                        <input type="hidden" name="item_type[]" value="<?php echo htmlspecialchars($item['item_type']); ?>">
                                                        <input type="hidden" name="item_id[]" value="<?php echo htmlspecialchars($item['item_id'] ?? ''); ?>">
                                                        <input type="text" class="form-control" name="description[]" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control item-quantity" name="quantity[]" min="1" value="<?php echo $item['quantity']; ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.01" class="form-control item-price" name="unit_price[]" value="<?php echo $item['unit_price']; ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.01" class="form-control item-total" name="total_price[]" value="<?php echo $item['total_price']; ?>" readonly>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button>
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
                                                    <input type="number" class="form-control item-quantity" name="quantity[]" min="1" value="1" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control item-price" name="unit_price[]" placeholder="0.00" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control item-total" name="total_price[]" placeholder="0.00" readonly>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5">
                                                <button type="button" class="btn btn-sm btn-success" id="add_item">
                                                    <i class="fas fa-plus"></i> Add Item
                                                </button>
                                                <button type="button" class="btn btn-sm btn-info ms-2" data-bs-toggle="modal" data-bs-target="#servicesModal">
                                                    <i class="fas fa-list"></i> Select from Services
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <!-- Totals Section -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes/Terms</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo ($action === 'edit') ? htmlspecialchars($invoice['notes']) : ''; ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row mb-2">
                                                <div class="col-6">
                                                    <strong>Subtotal:</strong>
                                                </div>
                                                <div class="col-6 text-end">
                                                    ₹<span id="subtotal">0.00</span>
                                                </div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-6">
                                                    <label for="tax_amount">Tax Amount:</label>
                                                </div>
                                                <div class="col-6">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">₹</span>
                                                        <input type="number" step="0.01" class="form-control" id="tax_amount" name="tax_amount" value="<?php echo ($action === 'edit') ? $invoice['tax_amount'] : '0.00'; ?>">
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
                                                        <input type="number" step="0.01" class="form-control" id="discount_amount" name="discount_amount" value="<?php echo ($action === 'edit') ? $invoice['discount_amount'] : '0.00'; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="row mb-2">
                                                <div class="col-6">
                                                    <strong>Total:</strong>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <strong>₹<span id="grand_total">0.00</span></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="save_invoice" class="btn btn-primary">
                                    <?php echo ($action === 'new') ? 'Create Invoice' : 'Update Invoice'; ?>
                                </button>
                                <a href="billing_fix_for_xampp.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Services Modal -->
                <div class="modal fade" id="servicesModal" tabindex="-1" aria-labelledby="servicesModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="servicesModalLabel">Select Services</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="serviceSearch" placeholder="Search services...">
                                    <button class="btn btn-outline-secondary" type="button" id="searchServices">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="servicesTable">
                                        <thead>
                                            <tr>
                                                <th>Service Name</th>
                                                <th>Price (₹)</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($available_services as $service): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($service['name']); ?></td>
                                                    <td>₹<?php echo number_format($service['price'], 2); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary add-service-item" 
                                                            data-type="<?php echo htmlspecialchars($service['type']); ?>"
                                                            data-id="<?php echo htmlspecialchars($service['id'] ?? ''); ?>"
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
            <?php elseif ($action === 'view' || $action === 'print'): ?>
                <!-- View/Print Invoice -->
                <div class="card shadow-sm mb-4" id="invoice-container">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-4">
                            <div>
                                <h4 class="mb-0">INVOICE</h4>
                                <p class="text-muted mb-0"># INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></p>
                            </div>
                            <div class="text-end">
                                <?php if ($action === 'view'): ?>
                                    <div class="d-none d-print-block">
                                        <h4>AKIRA HOSPITAL</h4>
                                        <p class="mb-0">123 Hospital Avenue, Medical District</p>
                                        <p class="mb-0">New Delhi, 110001</p>
                                        <p class="mb-0">Phone: +91 11 2222 3333</p>
                                    </div>
                                    <div class="d-print-none">
                                        <a href="billing_fix_for_xampp.php?action=print&id=<?php echo $invoice['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-print"></i> Print Mode
                                        </a>
                                        <a href="billing_fix_for_xampp.php?action=edit&id=<?php echo $invoice['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                            <i class="fas fa-money-bill-wave"></i> Record Payment
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <h4>AKIRA HOSPITAL</h4>
                                        <p class="mb-0">123 Hospital Avenue, Medical District</p>
                                        <p class="mb-0">New Delhi, 110001</p>
                                        <p class="mb-0">Phone: +91 11 2222 3333</p>
                                    </div>
                                    <button class="btn btn-info mt-2" onclick="window.print()">Print Invoice</button>
                                    <a href="billing_fix_for_xampp.php?action=view&id=<?php echo $invoice['id']; ?>" class="btn btn-secondary mt-2">
                                        Exit Print Mode
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Billed To:</h6>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['patient_name']); ?></strong></p>
                                <?php if (!empty($invoice['patient_address'])): ?>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($invoice['patient_address'])); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($invoice['patient_phone'])): ?>
                                    <p class="mb-1">Phone: <?php echo htmlspecialchars($invoice['patient_phone']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($invoice['patient_email'])): ?>
                                    <p class="mb-1">Email: <?php echo htmlspecialchars($invoice['patient_email']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($invoice['medical_record_number'])): ?>
                                    <p class="mb-1">MRN: <?php echo htmlspecialchars($invoice['medical_record_number']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h6 class="text-muted mb-2">Invoice Details:</h6>
                                <p class="mb-1">Invoice Date: <strong><?php echo date('d-M-Y', strtotime($invoice['invoice_date'])); ?></strong></p>
                                <?php if (!empty($invoice['due_date'])): ?>
                                    <p class="mb-1">Due Date: <strong><?php echo date('d-M-Y', strtotime($invoice['due_date'])); ?></strong></p>
                                <?php endif; ?>
                                <p class="mb-1">Status: 
                                    <strong>
                                        <?php if ($invoice['payment_status'] === 'paid'): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php elseif ($invoice['payment_status'] === 'partially_paid'): ?>
                                            <span class="badge bg-warning text-dark">Partially Paid</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Pending</span>
                                        <?php endif; ?>
                                    </strong>
                                </p>
                                <?php if (!empty($invoice['payment_method'])): ?>
                                    <p class="mb-1">Payment Method: <strong><?php echo htmlspecialchars($invoice['payment_method']); ?></strong></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered mt-3">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $subtotal = 0;
                                    foreach ($invoice_items as $item): 
                                        $subtotal += $item['total_price'];
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td class="text-end">₹<?php echo number_format($item['total_price'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end">₹<?php echo number_format($subtotal, 2); ?></td>
                                    </tr>
                                    <?php if (!empty($invoice['tax_amount']) && $invoice['tax_amount'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end">Tax:</td>
                                            <td class="text-end">₹<?php echo number_format($invoice['tax_amount'], 2); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($invoice['discount_amount']) && $invoice['discount_amount'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end">Discount:</td>
                                            <td class="text-end">-₹<?php echo number_format($invoice['discount_amount'], 2); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                        <td class="text-end"><strong>₹<?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                                    </tr>
                                    <?php if ($invoice['paid_amount'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end">Amount Paid:</td>
                                            <td class="text-end">₹<?php echo number_format($invoice['paid_amount'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Balance Due:</strong></td>
                                            <td class="text-end"><strong>₹<?php echo number_format(max(0, $invoice['total_amount'] - $invoice['paid_amount']), 2); ?></strong></td>
                                        </tr>
                                    <?php endif; ?>
                                </tfoot>
                            </table>
                        </div>
                        
                        <?php if (!empty($invoice['notes'])): ?>
                            <div class="mt-4">
                                <h6>Notes / Terms:</h6>
                                <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4 mb-4 text-center">
                            <p class="mb-0">Thank you for choosing AKIRA HOSPITAL for your healthcare needs.</p>
                            <p class="mb-0">For any billing inquiries, please contact our billing department at billing@akirahospital.com</p>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Modal -->
                <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="billing_fix_for_xampp.php?action=view&id=<?php echo $invoice['id']; ?>" method="post">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="paymentModalLabel">Record Payment</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="paid_amount" class="form-label">Payment Amount (₹) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" id="paid_amount" name="paid_amount" 
                                               value="<?php echo number_format(max(0, $invoice['total_amount'] - $invoice['paid_amount']), 2, '.', ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                        <select class="form-select" id="payment_method" name="payment_method" required>
                                            <option value="">-- Select Payment Method --</option>
                                            <option value="Cash">Cash</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="Debit Card">Debit Card</option>
                                            <option value="UPI">UPI</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Insurance">Insurance</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="record_payment" class="btn btn-success">Record Payment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Bootstrap JS and jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($action === 'new' || $action === 'edit'): ?>
<script>
    // Update item total when quantity or price changes
    $(document).on('input', '.item-quantity, .item-price', function() {
        var row = $(this).closest('tr');
        var quantity = parseFloat(row.find('.item-quantity').val()) || 0;
        var price = parseFloat(row.find('.item-price').val()) || 0;
        var total = quantity * price;
        row.find('.item-total').val(total.toFixed(2));
        updateTotals();
    });
    
    // Remove item row
    $(document).on('click', '.remove-item', function() {
        // Don't remove if it's the only row
        if ($('#invoice_items tbody tr').length > 1) {
            $(this).closest('tr').remove();
            updateTotals();
        } else {
            alert("You need at least one item in the invoice");
        }
    });
    
    // Add empty item row
    $('#add_item').click(function() {
        var newRow = `
            <tr>
                <td>
                    <input type="hidden" name="item_type[]" value="service">
                    <input type="hidden" name="item_id[]" value="">
                    <input type="text" class="form-control" name="description[]" placeholder="Enter description" required>
                </td>
                <td>
                    <input type="number" class="form-control item-quantity" name="quantity[]" min="1" value="1" required>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control item-price" name="unit_price[]" placeholder="0.00" required>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control item-total" name="total_price[]" placeholder="0.00" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
        $('#invoice_items tbody').append(newRow);
    });
    
    // Add service item from modal
    $(document).on('click', '.add-service-item', function() {
        var type = $(this).data('type');
        var id = $(this).data('id');
        var name = $(this).data('name');
        var price = parseFloat($(this).data('price'));
        
        var newRow = `
            <tr>
                <td>
                    <input type="hidden" name="item_type[]" value="${type}">
                    <input type="hidden" name="item_id[]" value="${id || ''}">
                    <input type="text" class="form-control" name="description[]" value="${name}" required>
                </td>
                <td>
                    <input type="number" class="form-control item-quantity" name="quantity[]" min="1" value="1" required>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control item-price" name="unit_price[]" value="${price.toFixed(2)}" required>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control item-total" name="total_price[]" value="${price.toFixed(2)}" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
        $('#invoice_items tbody').append(newRow);
        updateTotals();
        $('#servicesModal').modal('hide');
    });
    
    // Search services
    $('#searchServices').click(function() {
        var searchTerm = $('#serviceSearch').val().toLowerCase();
        $('#servicesTable tbody tr').each(function() {
            var serviceName = $(this).find('td:first').text().toLowerCase();
            if (serviceName.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Filter on key press
    $('#serviceSearch').keyup(function() {
        $('#searchServices').click();
    });
    
    // Calculate totals
    function updateTotals() {
        var subtotal = 0;
        $('.item-total').each(function() {
            subtotal += parseFloat($(this).val()) || 0;
        });
        
        $('#subtotal').text(subtotal.toFixed(2));
        
        var discount = parseFloat($('#discount_amount').val()) || 0;
        var tax = parseFloat($('#tax_amount').val()) || 0;
        var grandTotal = subtotal + tax - discount;
        
        $('#grand_total').text(grandTotal.toFixed(2));
    }
    
    // Update totals when tax or discount changes
    $('#tax_amount, #discount_amount').on('input', function() {
        updateTotals();
    });
    
    // Initial calculation
    updateTotals();
</script>
<?php endif; ?>

<?php if ($action === 'print'): ?>
<style>
    @media print {
        body {
            padding: 0;
            margin: 0;
        }
        .btn, .d-print-none, .navbar, footer, .sidebar {
            display: none !important;
        }
        .container-fluid {
            width: 100%;
            padding: 0;
            margin: 0;
        }
        .main {
            margin-left: 0 !important;
            padding: 0 !important;
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
<script>
    // Auto print when in print mode
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>
<?php endif; ?>
</body>
</html>