<?php
require_once 'includes/auth_check.php';
require_once 'db_connect.php';

// Get the action type
$action = isset($_GET['action']) ? $_GET['action'] : 'single_report';

if ($action === 'daily_report') {
    // Get today's date in Y-m-d format
    $today = date('Y-m-d');
    
    try {
        // Get all orders for today
        $stmt = $pdo->prepare("
            SELECT plt.*, 
                   p.name as patient_name, p.gender, p.age, p.phone,
                   lt.name as test_name, lt.description as test_description,
                   d.name as doctor_name,
                   ld.name as department_name,
                   DATE_FORMAT(plt.test_date, '%d-%m-%Y') as formatted_test_date,
                   DATE_FORMAT(plt.result_date, '%d-%m-%Y') as formatted_result_date
            FROM patient_lab_tests plt
            JOIN patients p ON plt.patient_id = p.id
            JOIN lab_tests lt ON plt.lab_test_id = lt.id
            JOIN lab_departments ld ON lt.lab_department_id = ld.id
            LEFT JOIN doctors d ON plt.requested_by = d.id
            WHERE DATE(plt.test_date) = ?
            ORDER BY plt.department_name, plt.test_date
        ");
        
        $stmt->execute([$today]);
        $daily_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($daily_orders)) {
            die("No laboratory tests found for today.");
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    // Get the order ID for single report
    $order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($order_id <= 0) {
        die("Invalid order ID");
    }
    
    try {
        // Get order details with all related information
        $stmt = $pdo->prepare("
            SELECT plt.*, 
                   p.name as patient_name, p.gender, p.age, p.phone,
                   lt.name as test_name, lt.description as test_description,
                   d.name as doctor_name,
                   ld.name as department_name,
                   DATE_FORMAT(plt.test_date, '%d-%m-%Y') as formatted_test_date,
                   DATE_FORMAT(plt.result_date, '%d-%m-%Y') as formatted_result_date
            FROM patient_lab_tests plt
            JOIN patients p ON plt.patient_id = p.id
            JOIN lab_tests lt ON plt.lab_test_id = lt.id
            JOIN lab_departments ld ON lt.lab_department_id = ld.id
            LEFT JOIN doctors d ON plt.requested_by = d.id
            WHERE plt.id = ?
        ");
        
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            die("Order not found");
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'daily_report' ? 'Daily Laboratory Report' : 'Lab Test Results'; ?> - AKIRA HOSPITAL</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @media print {
            body {
                padding: 20px;
                font-size: 14px;
            }
            .no-print {
                display: none !important;
            }
            .print-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .hospital-name {
                font-size: 24px;
                font-weight: bold;
                color: #0c4c8a;
                margin-bottom: 5px;
            }
            .department-name {
                font-size: 18px;
                color: #666;
                margin-bottom: 20px;
            }
            .report-title {
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #0c4c8a;
            }
            .patient-info {
                margin-bottom: 30px;
            }
            .results-section {
                margin-top: 20px;
                padding: 15px;
                border: 1px solid #ddd;
                background-color: #f9f9f9;
            }
            .footer {
                margin-top: 50px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            .signature-line {
                margin-top: 40px;
                border-top: 1px solid #000;
                width: 200px;
                text-align: center;
                padding-top: 5px;
            }
            .table th {
                background-color: #f8f9fa !important;
            }
            @page {
                size: A4;
                margin: 1cm;
            }
        }
        /* Styles for preview */
        .preview-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .table {
            font-size: 14px;
        }
        .table th {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="btn btn-primary print-button no-print">
        <i class="fas fa-print"></i> Print Report
    </button>
    
    <div class="preview-container">
        <div class="print-header">
            <div class="hospital-name">AKIRA HOSPITAL</div>
            <?php if ($action === 'daily_report'): ?>
                <div class="report-title">Daily Laboratory Report</div>
                <div class="department-name">Date: <?php echo date('d-m-Y'); ?></div>
            <?php else: ?>
                <div class="department-name"><?php echo htmlspecialchars($order['department_name']); ?> Department</div>
                <div class="report-title">Laboratory Test Report</div>
            <?php endif; ?>
        </div>
        
        <?php if ($action === 'daily_report'): ?>
            <!-- Daily Report Table -->
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Test ID</th>
                            <th>Patient Name</th>
                            <th>Test Name</th>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_orders as $test): ?>
                            <tr>
                                <td>#<?php echo $test['id']; ?></td>
                                <td><?php echo htmlspecialchars($test['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($test['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($test['department_name']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo match($test['status']) {
                                            'completed' => 'bg-success',
                                            'in_progress' => 'bg-warning',
                                            default => 'bg-secondary'
                                        };
                                    ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $test['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary Section -->
            <div class="mt-4">
                <h5>Summary</h5>
                <?php
                $total = count($daily_orders);
                $completed = count(array_filter($daily_orders, fn($test) => $test['status'] === 'completed'));
                $in_progress = count(array_filter($daily_orders, fn($test) => $test['status'] === 'in_progress'));
                $pending = count(array_filter($daily_orders, fn($test) => $test['status'] === 'requested'));
                ?>
                <p>Total Tests: <?php echo $total; ?></p>
                <p>Completed: <?php echo $completed; ?></p>
                <p>In Progress: <?php echo $in_progress; ?></p>
                <p>Pending: <?php echo $pending; ?></p>
            </div>
            
        <?php else: ?>
            <!-- Single Test Report -->
            <div class="patient-info">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($order['patient_name']); ?></p>
                        <p><strong>Age/Gender:</strong> <?php echo htmlspecialchars($order['age']); ?> / <?php echo htmlspecialchars($order['gender']); ?></p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Test ID:</strong> #<?php echo $order['id']; ?></p>
                        <p><strong>Test Date:</strong> <?php echo htmlspecialchars($order['formatted_test_date']); ?></p>
                        <p><strong>Report Date:</strong> <?php echo htmlspecialchars($order['formatted_result_date'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <p><strong>Referring Doctor:</strong> Dr. <?php echo htmlspecialchars($order['doctor_name']); ?></p>
                        <p><strong>Test Name:</strong> <?php echo htmlspecialchars($order['test_name']); ?></p>
                        <?php if (!empty($order['test_description'])): ?>
                        <p><strong>Test Description:</strong> <?php echo htmlspecialchars($order['test_description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="results-section">
                <h5 class="mb-3">Test Results</h5>
                <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($order['results'] ?? 'Results pending...'); ?></div>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Generated on:</strong> <?php echo date('d-m-Y H:i:s'); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="signature-line">
                        Laboratory Technician's Signature
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto print when loaded
        window.onload = function() {
            if (window.location.hash === '#print') {
                window.print();
            }
        };
    </script>
</body>
</html> 