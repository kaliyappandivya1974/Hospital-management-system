<?php
/**
 * AKIRA HOSPITAL Management System
 * Manage Doctor Schedules
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

// Set error/success messages
$error = null;
$success = null;

// Get action (if any)
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Create doctor_schedules table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'doctor_schedules'")) {
        db_query("
            CREATE TABLE IF NOT EXISTS doctor_schedules (
                id SERIAL PRIMARY KEY,
                doctor_id INT NOT NULL,
                day_of_week VARCHAR(20) NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                location VARCHAR(100) NULL,
                max_appointments INT NOT NULL DEFAULT 10,
                is_active BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
            )
        ");
    }
} catch (PDOException $e) {
    $error = "Database setup error: " . $e->getMessage();
}

// Get list of doctors
$doctors = [];
try {
    $doctors = db_get_rows("SELECT id, name, specialization FROM doctors ORDER BY name");
} catch (PDOException $e) {
    $error = "Error fetching doctors: " . $e->getMessage();
}

// Handle form submission for adding/editing schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_schedule']) || isset($_POST['edit_schedule'])) {
            $doctor_id = intval($_POST['doctor_id'] ?? 0);
            $day_of_week = $_POST['day_of_week'] ?? '';
            $start_time = $_POST['start_time'] ?? '';
            $end_time = $_POST['end_time'] ?? '';
            $location = $_POST['location'] ?? '';
            $max_appointments = intval($_POST['max_appointments'] ?? 10);
            $is_active = isset($_POST['is_active']) ? true : false;
            
            // Validation
            if ($doctor_id <= 0) {
                throw new Exception("Please select a doctor");
            }
            if (empty($day_of_week)) {
                throw new Exception("Day of week is required");
            }
            if (empty($start_time)) {
                throw new Exception("Start time is required");
            }
            if (empty($end_time)) {
                throw new Exception("End time is required");
            }
            
            // Convert times to 24-hour format if needed
            $start_time = date('H:i:s', strtotime($start_time));
            $end_time = date('H:i:s', strtotime($end_time));
            
            // Check if end time is after start time
            if (strtotime($end_time) <= strtotime($start_time)) {
                throw new Exception("End time must be after start time");
            }
            
            if (isset($_POST['add_schedule'])) {
                // Check if a schedule already exists for this doctor on this day
                $existing = db_get_row(
                    "SELECT id FROM doctor_schedules WHERE doctor_id = :doctor_id AND day_of_week = :day_of_week", 
                    [':doctor_id' => $doctor_id, ':day_of_week' => $day_of_week]
                );
                
                if ($existing) {
                    throw new Exception("A schedule for this doctor on $day_of_week already exists. Please edit the existing schedule instead.");
                }
                
                $data = [
                    'doctor_id' => $doctor_id,
                    'day_of_week' => $day_of_week,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'location' => $location,
                    'max_appointments' => $max_appointments,
                    'is_active' => $is_active
                ];
                
                $schedule_id = db_insert('doctor_schedules', $data);
                
                if ($schedule_id) {
                    $success = "Schedule added successfully";
                } else {
                    throw new Exception("Failed to add schedule");
                }
            } else if (isset($_POST['edit_schedule'])) {
                $schedule_id = intval($_POST['schedule_id'] ?? 0);
                
                if ($schedule_id <= 0) {
                    throw new Exception("Invalid schedule ID");
                }
                
                $data = [
                    'doctor_id' => $doctor_id,
                    'day_of_week' => $day_of_week,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'location' => $location,
                    'max_appointments' => $max_appointments,
                    'is_active' => $is_active
                ];
                
                if (db_update('doctor_schedules', $data, 'id = :id', [':id' => $schedule_id])) {
                    $success = "Schedule updated successfully";
                } else {
                    throw new Exception("Failed to update schedule");
                }
            }
            
            // Redirect to list view to avoid form resubmission
            header("Location: manage_schedule.php?success=" . urlencode($success));
            exit;
        } else if (isset($_POST['delete_schedule'])) {
            $schedule_id = intval($_POST['schedule_id'] ?? 0);
            
            if ($schedule_id <= 0) {
                throw new Exception("Invalid schedule ID");
            }
            
            // Check if there are appointments linked to this schedule
            $appointments = db_get_row("
                SELECT COUNT(*) as count FROM appointments 
                WHERE schedule_id = :schedule_id", 
                [':schedule_id' => $schedule_id]
            );
            
            if ($appointments && $appointments['count'] > 0) {
                throw new Exception("Cannot delete schedule with existing appointments");
            }
            
            if (db_delete('doctor_schedules', 'id = :id', [':id' => $schedule_id])) {
                $success = "Schedule deleted successfully";
                header("Location: manage_schedule.php?success=" . urlencode($success));
                exit;
            } else {
                throw new Exception("Failed to delete schedule");
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get schedule details if editing
$schedule = [];
if ($action === 'edit' && $schedule_id > 0) {
    $schedule = db_get_row("SELECT * FROM doctor_schedules WHERE id = :id", [':id' => $schedule_id]);
    if (!$schedule) {
        $error = "Schedule not found";
        $action = 'list';
    }
}

// Get the list of schedules for all doctors or a specific doctor
$schedules = [];
try {
    $query = "
        SELECT ds.*, d.name as doctor_name, d.specialization 
        FROM doctor_schedules ds
        INNER JOIN doctors d ON ds.doctor_id = d.id
    ";
    $params = [];
    
    if ($doctor_id > 0) {
        $query .= " WHERE ds.doctor_id = :doctor_id";
        $params[':doctor_id'] = $doctor_id;
    }
    
    $query .= " ORDER BY d.name, CASE 
                    WHEN ds.day_of_week = 'Monday' THEN 1
                    WHEN ds.day_of_week = 'Tuesday' THEN 2
                    WHEN ds.day_of_week = 'Wednesday' THEN 3
                    WHEN ds.day_of_week = 'Thursday' THEN 4
                    WHEN ds.day_of_week = 'Friday' THEN 5
                    WHEN ds.day_of_week = 'Saturday' THEN 6
                    WHEN ds.day_of_week = 'Sunday' THEN 7
                    ELSE 8
                END, ds.start_time";
    
    $schedules = db_get_rows($query, $params);
} catch (PDOException $e) {
    $error = "Error fetching schedules: " . $e->getMessage();
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
                <h1 class="h2">Manage Doctor Schedules</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <?php if ($action !== 'add' && $action !== 'edit'): ?>
                            <a href="manage_schedule.php?action=add" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-plus"></i> Add New Schedule
                            </a>
                        <?php endif; ?>
                        <a href="appointments.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-calendar-check"></i> Appointments
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit Schedule Form -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo ($action === 'add') ? 'Add New Schedule' : 'Edit Schedule'; ?></h5>
                        
                        <form action="manage_schedule.php" method="post">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="doctor_id" class="form-label">Doctor <span class="text-danger">*</span></label>
                                    <select class="form-control" id="doctor_id" name="doctor_id" required <?php echo ($action === 'edit') ? 'disabled' : ''; ?>>
                                        <option value="">-- Select Doctor --</option>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <option value="<?php echo $doctor['id']; ?>" <?php echo (($action === 'edit' && $schedule['doctor_id'] == $doctor['id']) || ($doctor_id > 0 && $doctor_id == $doctor['id'])) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($doctor['name']); ?> (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($action === 'edit'): ?>
                                        <input type="hidden" name="doctor_id" value="<?php echo $schedule['doctor_id']; ?>">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="day_of_week" class="form-label">Day of Week <span class="text-danger">*</span></label>
                                    <select class="form-control" id="day_of_week" name="day_of_week" required <?php echo ($action === 'edit') ? 'disabled' : ''; ?>>
                                        <option value="">-- Select Day --</option>
                                        <option value="Monday" <?php echo ($action === 'edit' && $schedule['day_of_week'] === 'Monday') ? 'selected' : ''; ?>>Monday</option>
                                        <option value="Tuesday" <?php echo ($action === 'edit' && $schedule['day_of_week'] === 'Tuesday') ? 'selected' : ''; ?>>Tuesday</option>
                                        <option value="Wednesday" <?php echo ($action === 'edit' && $schedule['day_of_week'] === 'Wednesday') ? 'selected' : ''; ?>>Wednesday</option>
                                        <option value="Thursday" <?php echo ($action === 'edit' && $schedule['day_of_week'] === 'Thursday') ? 'selected' : ''; ?>>Thursday</option>
                                        <option value="Friday" <?php echo ($action === 'edit' && $schedule['day_of_week'] === 'Friday') ? 'selected' : ''; ?>>Friday</option>
                                        <option value="Saturday" <?php echo ($action === 'edit' && $schedule['day_of_week'] === 'Saturday') ? 'selected' : ''; ?>>Saturday</option>
                                        <option value="Sunday" <?php echo ($action === 'edit' && $schedule['day_of_week'] === 'Sunday') ? 'selected' : ''; ?>>Sunday</option>
                                    </select>
                                    <?php if ($action === 'edit'): ?>
                                        <input type="hidden" name="day_of_week" value="<?php echo $schedule['day_of_week']; ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" required value="<?php echo ($action === 'edit') ? date('H:i', strtotime($schedule['start_time'])) : '09:00'; ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" required value="<?php echo ($action === 'edit') ? date('H:i', strtotime($schedule['end_time'])) : '17:00'; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" name="location" value="<?php echo ($action === 'edit') ? htmlspecialchars($schedule['location']) : ''; ?>" placeholder="e.g., Room 101, OPD Wing">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="max_appointments" class="form-label">Max Appointments</label>
                                    <input type="number" class="form-control" id="max_appointments" name="max_appointments" min="1" max="50" value="<?php echo ($action === 'edit') ? $schedule['max_appointments'] : 10; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo ($action === 'edit' && $schedule['is_active']) || $action === 'add' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active Schedule</label>
                            </div>
                            
                            <div class="mb-3">
                                <button type="submit" name="<?php echo ($action === 'add') ? 'add_schedule' : 'edit_schedule'; ?>" class="btn btn-primary">
                                    <?php echo ($action === 'add') ? 'Add Schedule' : 'Update Schedule'; ?>
                                </button>
                                <a href="manage_schedule.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Filter Form -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form action="manage_schedule.php" method="get" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="filter_doctor" class="form-label">Filter by Doctor</label>
                                <select class="form-control" id="filter_doctor" name="doctor_id">
                                    <option value="">All Doctors</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>" <?php echo ($doctor_id > 0 && $doctor_id == $doctor['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($doctor['name']); ?> (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <?php if ($doctor_id > 0): ?>
                                    <a href="manage_schedule.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Schedules List -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if (empty($schedules)): ?>
                            <div class="alert alert-info mb-0">No schedules found. Please add a new schedule.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Doctor</th>
                                            <th>Day</th>
                                            <th>Time</th>
                                            <th>Location</th>
                                            <th>Max. Appointments</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedules as $s): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($s['doctor_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($s['specialization']); ?></small></td>
                                                <td><?php echo htmlspecialchars($s['day_of_week']); ?></td>
                                                <td><?php echo date('h:i A', strtotime($s['start_time'])); ?> - <?php echo date('h:i A', strtotime($s['end_time'])); ?></td>
                                                <td><?php echo !empty($s['location']) ? htmlspecialchars($s['location']) : '<span class="text-muted">Not specified</span>'; ?></td>
                                                <td><?php echo $s['max_appointments']; ?></td>
                                                <td>
                                                    <?php if ($s['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="manage_schedule.php?action=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $s['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Confirmation Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $s['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $s['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $s['id']; ?>">Confirm Delete</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete the schedule for <strong><?php echo htmlspecialchars($s['doctor_name']); ?></strong> on <strong><?php echo htmlspecialchars($s['day_of_week']); ?></strong>?
                                                                    <p class="text-danger mt-2"><strong>Warning:</strong> This action cannot be undone.</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <form action="manage_schedule.php" method="post">
                                                                        <input type="hidden" name="schedule_id" value="<?php echo $s['id']; ?>">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" name="delete_schedule" class="btn btn-danger">Delete</button>
                                                                    </form>
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
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>