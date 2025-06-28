<?php
/**
 * AKIRA HOSPITAL Management System
 * Prescription Management Page
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
$prescription_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

// Get appointment details if specified
$appointment = [];
if ($appointment_id > 0) {
    try {
        $sql = "SELECT a.*, p.name as patient_name, d.name as doctor_name 
                FROM appointments a 
                LEFT JOIN patients p ON a.patient_id = p.id 
                LEFT JOIN doctors d ON a.doctor_id = d.id 
                WHERE a.id = :id";
        $appointment = db_get_row($sql, [':id' => $appointment_id]);
        
        if (!$appointment) {
            $error = "Appointment not found";
            $action = 'list'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'list'; // Fallback to list
    }
}

// Define the page title based on action
$page_title = "Prescriptions";
if ($action === 'new') {
    $page_title = "Create New Prescription";
} elseif ($action === 'edit') {
    $page_title = "Edit Prescription";
} elseif ($action === 'view') {
    $page_title = "Prescription Details";
}

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $page_title; ?></h1>
                <div class="user-welcome">
                    Welcome, <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                    <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($admin_role); ?></span>
                </div>
            </div>
            
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
            
            <?php if ($action === 'new' && $appointment): ?>
                <!-- Create Prescription Form -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Create Prescription</h5>
                        <a href="appointments.php?action=view&id=<?php echo $appointment_id; ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Appointment
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">Appointment Information</h6>
                                <table class="table">
                                    <tr>
                                        <th width="30%">Patient</th>
                                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Doctor</th>
                                        <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date & Time</th>
                                        <td><?php echo date('d M Y, h:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Reason</th>
                                        <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <form action="prescriptions.php?action=new&appointment_id=<?php echo $appointment_id; ?>" method="post">
                            <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $appointment['patient_id']; ?>">
                            <input type="hidden" name="doctor_id" value="<?php echo $appointment['doctor_id']; ?>">
                            
                            <div class="mb-4">
                                <h6 class="mb-3">Diagnosis</h6>
                                <div class="mb-3">
                                    <label for="diagnosis" class="form-label">Diagnosis/Condition <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required></textarea>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="mb-3">Medicines</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="medicinesTable">
                                        <thead>
                                            <tr>
                                                <th>Medicine</th>
                                                <th>Dosage</th>
                                                <th>Frequency</th>
                                                <th>Duration</th>
                                                <th>Instructions</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="medicineRows">
                                            <tr id="medicineRow1">
                                                <td>
                                                    <input type="text" class="form-control" name="medicines[0][name]" required>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="medicines[0][dosage]" placeholder="e.g., 500mg" required>
                                                </td>
                                                <td>
                                                    <select class="form-control" name="medicines[0][frequency]" required>
                                                        <option value="Once daily">Once daily</option>
                                                        <option value="Twice daily">Twice daily</option>
                                                        <option value="Three times daily">Three times daily</option>
                                                        <option value="Four times daily">Four times daily</option>
                                                        <option value="Every 6 hours">Every 6 hours</option>
                                                        <option value="Every 8 hours">Every 8 hours</option>
                                                        <option value="Every 12 hours">Every 12 hours</option>
                                                        <option value="As needed">As needed</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="medicines[0][duration_number]" min="1" value="7" required>
                                                        <select class="form-control" name="medicines[0][duration_unit]" required>
                                                            <option value="days">Days</option>
                                                            <option value="weeks">Weeks</option>
                                                            <option value="months">Months</option>
                                                        </select>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="medicines[0][instructions]" placeholder="e.g., After meals">
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeMedicineRow(this)" disabled>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="addMedicineRow()">
                                        <i class="fas fa-plus"></i> Add Medicine
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="mb-3">Additional Information</h6>
                                <div class="mb-3">
                                    <label for="additional_instructions" class="form-label">Additional Instructions</label>
                                    <textarea class="form-control" id="additional_instructions" name="additional_instructions" rows="3"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="follow_up" class="form-label">Follow Up</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="follow_up_number" name="follow_up_number" min="0">
                                        <select class="form-control" id="follow_up_unit" name="follow_up_unit">
                                            <option value="days">Days</option>
                                            <option value="weeks">Weeks</option>
                                            <option value="months">Months</option>
                                        </select>
                                    </div>
                                    <div class="form-text">Leave blank if no follow-up is needed</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <a href="appointments.php?action=view&id=<?php echo $appointment_id; ?>" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" name="save_prescription" class="btn btn-primary">Create Prescription</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($action === 'list'): ?>
                <!-- Prescription List -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This is a placeholder for the prescriptions list view. This functionality is under development.
                </div>
                
                <div class="text-center py-5">
                    <i class="fas fa-prescription fa-4x text-muted mb-3"></i>
                    <h4>Prescriptions Management</h4>
                    <p class="text-muted">
                        You can create prescriptions from the appointment details page.<br>
                        Select an appointment and click the "Create Prescription" button.
                    </p>
                    <a href="appointments.php" class="btn btn-primary mt-3">
                        <i class="fas fa-calendar-check me-2"></i> Go to Appointments
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Invalid action or missing appointment information.
                </div>
                <div class="text-center py-5">
                    <a href="appointments.php" class="btn btn-primary">
                        <i class="fas fa-calendar-check me-2"></i> Go to Appointments
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    let medicineRowCount = 1;
    
    function addMedicineRow() {
        medicineRowCount++;
        const index = medicineRowCount - 1;
        
        const newRow = document.createElement('tr');
        newRow.id = 'medicineRow' + medicineRowCount;
        newRow.innerHTML = `
            <td>
                <input type="text" class="form-control" name="medicines[${index}][name]" required>
            </td>
            <td>
                <input type="text" class="form-control" name="medicines[${index}][dosage]" placeholder="e.g., 500mg" required>
            </td>
            <td>
                <select class="form-control" name="medicines[${index}][frequency]" required>
                    <option value="Once daily">Once daily</option>
                    <option value="Twice daily">Twice daily</option>
                    <option value="Three times daily">Three times daily</option>
                    <option value="Four times daily">Four times daily</option>
                    <option value="Every 6 hours">Every 6 hours</option>
                    <option value="Every 8 hours">Every 8 hours</option>
                    <option value="Every 12 hours">Every 12 hours</option>
                    <option value="As needed">As needed</option>
                </select>
            </td>
            <td>
                <div class="input-group">
                    <input type="number" class="form-control" name="medicines[${index}][duration_number]" min="1" value="7" required>
                    <select class="form-control" name="medicines[${index}][duration_unit]" required>
                        <option value="days">Days</option>
                        <option value="weeks">Weeks</option>
                        <option value="months">Months</option>
                    </select>
                </div>
            </td>
            <td>
                <input type="text" class="form-control" name="medicines[${index}][instructions]" placeholder="e.g., After meals">
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeMedicineRow(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        document.getElementById('medicineRows').appendChild(newRow);
        
        // Enable delete button on first row if there are multiple rows
        if (medicineRowCount > 1) {
            document.querySelector('#medicineRow1 button').removeAttribute('disabled');
        }
    }
    
    function removeMedicineRow(button) {
        const row = button.closest('tr');
        row.remove();
        medicineRowCount--;
        
        // Disable delete button on first row if it's the only row left
        if (medicineRowCount === 1) {
            document.querySelector('#medicineRow1 button').setAttribute('disabled', 'disabled');
        }
    }
</script>

<?php include_once 'includes/footer.php'; ?>