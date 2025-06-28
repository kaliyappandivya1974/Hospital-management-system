<?php
/**
 * AKIRA HOSPITAL Management System
 * Staff Management Page - Fixed Version
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
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Define staff categories
$staff_categories = [
    1 => 'Doctor',
    2 => 'Nurse',
    3 => 'Receptionist',
    4 => 'Lab Technician',
    5 => 'Pharmacist',
    6 => 'Administrator',
    7 => 'Accountant',
    8 => 'Maintenance',
    9 => 'Security',
    10 => 'Other'
];

// Define departments
$departments = [
    1 => 'General Medicine',
    2 => 'Cardiology',
    3 => 'Neurology',
    4 => 'Pediatrics',
    5 => 'Orthopedics',
    6 => 'Gynecology',
    7 => 'Oncology',
    8 => 'Dermatology',
    9 => 'Psychiatry',
    10 => 'Administration',
    11 => 'Pharmacy',
    12 => 'Laboratory',
    13 => 'Radiology',
    14 => 'Emergency'
];

// Create staff_categories table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'staff_categories'")) {
        // Check DB type - MySQL or PostgreSQL
        $is_mysql = db_get_row("SHOW VARIABLES LIKE 'version'");
        
        if ($is_mysql) {
            // MySQL version
            db_query("
                CREATE TABLE IF NOT EXISTS staff_categories (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    description TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            // PostgreSQL version
            db_query("
                CREATE TABLE IF NOT EXISTS staff_categories (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
        
        // Insert default categories
        foreach ($staff_categories as $id => $name) {
            db_query("INSERT INTO staff_categories (id, name) VALUES (:id, :name)", [
                ':id' => $id,
                ':name' => $name
            ]);
        }
        
        error_log("Staff categories table created with default values");
    }
} catch (PDOException $e) {
    error_log("Error creating staff_categories table: " . $e->getMessage());
}

// Create departments table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'departments'")) {
        // Check DB type - MySQL or PostgreSQL
        $is_mysql = db_get_row("SHOW VARIABLES LIKE 'version'");
        
        if ($is_mysql) {
            // MySQL version
            db_query("
                CREATE TABLE IF NOT EXISTS departments (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    description TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            // PostgreSQL version
            db_query("
                CREATE TABLE IF NOT EXISTS departments (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
        
        // Insert default departments
        foreach ($departments as $id => $name) {
            db_query("INSERT INTO departments (id, name) VALUES (:id, :name)", [
                ':id' => $id,
                ':name' => $name
            ]);
        }
        
        error_log("Departments table created with default values");
    }
} catch (PDOException $e) {
    error_log("Error creating departments table: " . $e->getMessage());
}

// Create staff table if it doesn't exist
try {
    if (!db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'staff'")) {
        // Check DB type - MySQL or PostgreSQL
        $is_mysql = db_get_row("SHOW VARIABLES LIKE 'version'");
        
        if ($is_mysql) {
            // MySQL version
            db_query("
                CREATE TABLE IF NOT EXISTS staff (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    category_id INT NOT NULL,
                    department_id INT NULL,
                    gender ENUM('Male', 'Female', 'Other') NOT NULL,
                    date_of_birth DATE NOT NULL,
                    phone VARCHAR(20) NOT NULL,
                    email VARCHAR(100) NULL,
                    address TEXT NOT NULL,
                    qualification VARCHAR(100) NOT NULL,
                    hire_date DATE NOT NULL,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES staff_categories(id) ON DELETE RESTRICT,
                    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
                )
            ");
        } else {
            // PostgreSQL version
            db_query("
                CREATE TABLE IF NOT EXISTS staff (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    category_id INT NOT NULL,
                    department_id INT NULL,
                    gender VARCHAR(10) NOT NULL CHECK (gender IN ('Male', 'Female', 'Other')),
                    date_of_birth DATE NOT NULL,
                    phone VARCHAR(20) NOT NULL,
                    email VARCHAR(100) NULL,
                    address TEXT NOT NULL,
                    qualification VARCHAR(100) NOT NULL,
                    hire_date DATE NOT NULL,
                    status VARCHAR(10) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES staff_categories(id) ON DELETE RESTRICT,
                    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
                )
            ");
        }
        
        error_log("Staff table created successfully");
        
        // Add some default staff
        $default_staff = [
            [
                'name' => 'John Smith',
                'category_id' => 6, // Administrator
                'department_id' => 10, // Administration
                'gender' => 'Male',
                'date_of_birth' => '1980-01-15',
                'phone' => '555-123-4567',
                'email' => 'john.smith@hospital.com',
                'address' => '123 Hospital Street, Medical City',
                'qualification' => 'MBA, Hospital Administration',
                'hire_date' => '2018-06-01',
                'status' => 'active'
            ],
            [
                'name' => 'Mary Johnson',
                'category_id' => 2, // Nurse
                'department_id' => 4, // Pediatrics
                'gender' => 'Female',
                'date_of_birth' => '1985-03-22',
                'phone' => '555-234-5678',
                'email' => 'mary.johnson@hospital.com',
                'address' => '456 Nursing Drive, Medical City',
                'qualification' => 'BSN, Pediatric Nursing',
                'hire_date' => '2019-02-15',
                'status' => 'active'
            ],
            [
                'name' => 'David Chen',
                'category_id' => 4, // Lab Technician
                'department_id' => 12, // Laboratory
                'gender' => 'Male',
                'date_of_birth' => '1990-07-12',
                'phone' => '555-345-6789',
                'email' => 'david.chen@hospital.com',
                'address' => '789 Science Blvd, Medical City',
                'qualification' => 'BS, Medical Laboratory Science',
                'hire_date' => '2020-09-01',
                'status' => 'active'
            ]
        ];
        
        foreach ($default_staff as $staff) {
            db_insert('staff', $staff);
        }
        
        error_log("Default staff entries added");
    }
} catch (PDOException $e) {
    error_log("Error creating staff table: " . $e->getMessage());
}

// Handle form submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $department_id = isset($_POST['department_id']) && !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $qualification = isset($_POST['qualification']) ? trim($_POST['qualification']) : '';
        $hire_date = isset($_POST['hire_date']) ? trim($_POST['hire_date']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
        
        // Validation
        if (empty($name) || empty($gender) || empty($date_of_birth) || empty($phone) || 
            empty($address) || empty($qualification) || empty($hire_date) || $category_id <= 0) {
            throw new Exception("Please fill all required fields");
        }
        
        // Different actions based on form submission
        if (isset($_POST['save_staff'])) {
            if ($action === 'edit' && $staff_id > 0) {
                // Update existing staff member
                $data = [
                    'name' => $name,
                    'category_id' => $category_id,
                    'department_id' => $department_id,
                    'gender' => $gender,
                    'date_of_birth' => $date_of_birth,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address,
                    'qualification' => $qualification,
                    'hire_date' => $hire_date,
                    'status' => $status
                ];
                
                db_update('staff', $data, ['id' => $staff_id]);
                $success = "Staff member updated successfully";
                
                // Redirect to staff list
                header("Location: staff_fixed.php");
                exit;
            } else {
                // Insert new staff member
                $data = [
                    'name' => $name,
                    'category_id' => $category_id,
                    'department_id' => $department_id,
                    'gender' => $gender,
                    'date_of_birth' => $date_of_birth,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address,
                    'qualification' => $qualification,
                    'hire_date' => $hire_date,
                    'status' => $status
                ];
                
                db_insert('staff', $data);
                $success = "Staff member added successfully";
                
                // Redirect to staff list
                header("Location: staff_fixed.php");
                exit;
            }
        } elseif (isset($_POST['delete_staff']) && $staff_id > 0) {
            // Delete staff
            db_query("DELETE FROM staff WHERE id = :id", [':id' => $staff_id]);
            $success = "Staff member deleted successfully";
            
            // Redirect to staff list
            header("Location: staff_fixed.php");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get list of staff
$staff_list = [];
try {
    $sql = "SELECT s.*, 
                   c.name as category_name, 
                   d.name as department_name 
            FROM staff s 
            LEFT JOIN staff_categories c ON s.category_id = c.id 
            LEFT JOIN departments d ON s.department_id = d.id 
            ORDER BY s.name ASC";
    $staff_list = db_get_rows($sql);
} catch (PDOException $e) {
    $error = "Error fetching staff: " . $e->getMessage();
}

// Get staff details for editing
$staff = [];
if ($action === 'edit' && $staff_id > 0) {
    try {
        $sql = "SELECT * FROM staff WHERE id = :id";
        $staff = db_get_row($sql, [':id' => $staff_id]);
        
        if (!$staff) {
            $error = "Staff member not found";
            $action = 'list'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'list'; // Fallback to list
    }
}

// Define the page title based on action
$page_title = "Staff Management";
if ($action === 'new') {
    $page_title = "Add New Staff Member";
} elseif ($action === 'edit') {
    $page_title = "Edit Staff Member";
} elseif ($action === 'view') {
    $page_title = "Staff Details";
}

// Include the header
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
                <!-- Staff Form -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?php echo $action === 'new' ? 'Add New Staff Member' : 'Edit Staff Member'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form action="staff_fixed.php?action=<?php echo $action; ?><?php echo $staff_id ? '&id=' . $staff_id : ''; ?>" method="post">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           value="<?php echo isset($staff['name']) ? htmlspecialchars($staff['name']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-control" id="category_id" name="category_id" required>
                                        <option value="">-- Select Category --</option>
                                        <?php foreach ($staff_categories as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" <?php echo (isset($staff['category_id']) && $staff['category_id'] == $id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="department_id" class="form-label">Department</label>
                                    <select class="form-control" id="department_id" name="department_id">
                                        <option value="">-- Select Department --</option>
                                        <?php foreach ($departments as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" <?php echo (isset($staff['department_id']) && $staff['department_id'] == $id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                    <select class="form-control" id="gender" name="gender" required>
                                        <option value="">-- Select Gender --</option>
                                        <option value="Male" <?php echo (isset($staff['gender']) && $staff['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($staff['gender']) && $staff['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (isset($staff['gender']) && $staff['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required
                                           value="<?php echo isset($staff['date_of_birth']) ? $staff['date_of_birth'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required
                                           value="<?php echo isset($staff['phone']) ? htmlspecialchars($staff['phone']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo isset($staff['email']) ? htmlspecialchars($staff['email']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="address" name="address" rows="2" required><?php echo isset($staff['address']) ? htmlspecialchars($staff['address']) : ''; ?></textarea>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="qualification" class="form-label">Qualification <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="qualification" name="qualification" required
                                           value="<?php echo isset($staff['qualification']) ? htmlspecialchars($staff['qualification']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" required
                                           value="<?php echo isset($staff['hire_date']) ? $staff['hire_date'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" <?php echo (!isset($staff['status']) || $staff['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo (isset($staff['status']) && $staff['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" name="save_staff" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $action === 'new' ? 'Add Staff Member' : 'Update Staff Member'; ?>
                                </button>
                                <a href="staff_fixed.php" class="btn btn-secondary">Cancel</a>
                                
                                <?php if ($action === 'edit'): ?>
                                    <button type="submit" name="delete_staff" class="btn btn-danger float-end" onclick="return confirm('Are you sure you want to delete this staff member?');">
                                        <i class="fas fa-trash"></i> Delete Staff Member
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Staff List -->
                <div class="mb-4 d-flex justify-content-end">
                    <a href="staff_fixed.php?action=new" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Staff Member
                    </a>
                </div>
                
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Staff Members</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="staffDataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Department</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($staff_list)): ?>
                                        <?php foreach ($staff_list as $staff_item): ?>
                                            <tr>
                                                <td><?php echo $staff_item['id']; ?></td>
                                                <td><?php echo htmlspecialchars($staff_item['name']); ?></td>
                                                <td><?php echo htmlspecialchars($staff_item['category_name']); ?></td>
                                                <td><?php echo !empty($staff_item['department_name']) ? htmlspecialchars($staff_item['department_name']) : 'N/A'; ?></td>
                                                <td><?php echo htmlspecialchars($staff_item['phone']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $staff_item['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo ucfirst($staff_item['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="staff_fixed.php?action=edit&id=<?php echo $staff_item['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $staff_item['id']; ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No staff members found</td>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this staff member? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    // Initialize DataTables
    $(document).ready(function() {
        $('#staffDataTable').DataTable();
    });
    
    // Function to handle delete confirmation
    function confirmDelete(staffId) {
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        confirmDeleteBtn.href = `staff_fixed.php?action=delete&id=${staffId}`;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
</script>

<?php include_once 'includes/footer.php'; ?>