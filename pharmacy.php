<?php
/**
 * AKIRA HOSPITAL Management System
 * Pharmacy Management Page
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
$medicine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Get list of medicine categories
$medicine_categories = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'medicine_categories'")) {
        $medicine_categories = db_get_rows("SELECT * FROM medicine_categories ORDER BY name ASC");
    } else {
        // Create medicine_categories table if it doesn't exist
        db_query("
            CREATE TABLE IF NOT EXISTS medicine_categories (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT NULL
            )
        ");
    }
} catch (PDOException $e) {
    error_log("Error fetching medicine categories: " . $e->getMessage());
}

// Get list of medicines
$medicines = [];
try {
    if (db_get_row("SELECT 1 FROM information_schema.tables WHERE table_name = 'medicines'")) {
        $query = "SELECT m.*, c.name as category_name 
                FROM medicines m 
                LEFT JOIN medicine_categories c ON m.category_id = c.id";
        
        if ($category_id > 0) {
            $query .= " WHERE m.category_id = :category_id";
            $medicines = db_get_rows($query, [':category_id' => $category_id]);
        } else {
            $medicines = db_get_rows($query);
        }
    } else {
        // Create medicines table if it doesn't exist
        db_query("
            CREATE TABLE IF NOT EXISTS medicines (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                category_id INT NULL,
                description TEXT NULL,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                stock_quantity INT NOT NULL DEFAULT 0,
                manufacturer VARCHAR(100) NULL,
                expiry_date DATE NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Add foreign key constraint if possible (may fail silently if medicine_categories doesn't exist yet)
        try {
            db_query("ALTER TABLE medicines ADD CONSTRAINT fk_medicine_category 
                     FOREIGN KEY (category_id) REFERENCES medicine_categories(id) 
                     ON DELETE SET NULL");
        } catch (Exception $e) {
            // Silently continue if this fails - we'll try again later
            error_log("Could not add foreign key constraint: " . $e->getMessage());
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching medicines: " . $e->getMessage());
}

// Handle form submission for new/edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    try {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        // Validation
        if (empty($name)) {
            $error = "Category name is required";
        } else {
            if (isset($_POST['category_id']) && intval($_POST['category_id']) > 0) {
                // Update existing category
                $sql = "UPDATE medicine_categories SET name = :name, description = :description 
                        WHERE id = :id";
                $params = [
                    ':name' => $name,
                    ':description' => $description,
                    ':id' => intval($_POST['category_id'])
                ];
                db_query($sql, $params);
                $success = "Category updated successfully";
            } else {
                // Insert new category
                $sql = "INSERT INTO medicine_categories (name, description) 
                        VALUES (:name, :description)";
                $params = [
                    ':name' => $name,
                    ':description' => $description
                ];
                db_query($sql, $params);
                $success = "Category added successfully";
            }
            // Redirect to category list
            header("Location: pharmacy.php?action=categories");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle form submission for new/edit medicine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_medicine'])) {
    try {
        $name = $_POST['name'] ?? '';
        $category_id = intval($_POST['category_id'] ?? 0);
        $description = $_POST['description'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
        $manufacturer = $_POST['manufacturer'] ?? '';
        $expiry_date = $_POST['expiry_date'] ?? null;
        
        // Validation
        if (empty($name)) {
            $error = "Medicine name is required";
        } else {
            if ($action === 'edit_medicine' && $medicine_id > 0) {
                // Update existing medicine
                $sql = "UPDATE medicines SET name = :name, category_id = :category_id, 
                        description = :description, unit_price = :unit_price, stock_quantity = :stock_quantity, 
                        manufacturer = :manufacturer, expiry_date = :expiry_date, status = :status
                        WHERE id = :id";
                $params = [
                    ':name' => $name,
                    ':category_id' => $category_id ?: null,
                    ':description' => $description,
                    ':unit_price' => $price,
                    ':stock_quantity' => $stock_quantity,
                    ':manufacturer' => $manufacturer,
                    ':expiry_date' => $expiry_date,
                    ':status' => 'active',
                    ':id' => $medicine_id
                ];
                db_query($sql, $params);
                $success = "Medicine updated successfully";
            } else {
                // Insert new medicine
                $sql = "INSERT INTO medicines (name, category_id, description, unit_price, stock_quantity, 
                        manufacturer, expiry_date, status) 
                        VALUES (:name, :category_id, :description, :unit_price, :stock_quantity, 
                        :manufacturer, :expiry_date, :status)";
                $params = [
                    ':name' => $name,
                    ':category_id' => $category_id ?: null,
                    ':description' => $description,
                    ':unit_price' => $price,
                    ':stock_quantity' => $stock_quantity,
                    ':manufacturer' => $manufacturer,
                    ':expiry_date' => $expiry_date,
                    ':status' => 'active'
                ];
                db_query($sql, $params);
                $success = "Medicine added successfully";
            }
            // Redirect to medicine list
            header("Location: pharmacy.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get medicine data for edit
$medicine = null;
if ($action === 'edit_medicine' && $medicine_id > 0) {
    try {
        $medicine = db_get_row("SELECT * FROM medicines WHERE id = :id", [':id' => $medicine_id]);
        if (!$medicine) {
            $error = "Medicine not found";
            $action = 'list'; // Fallback to list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'list'; // Fallback to list
    }
}

// Get category data for edit
$category = null;
if ($action === 'edit_category' && $category_id > 0) {
    try {
        $category = db_get_row("SELECT * FROM medicine_categories WHERE id = :id", [':id' => $category_id]);
        if (!$category) {
            $error = "Category not found";
            $action = 'categories'; // Fallback to categories list
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $action = 'categories'; // Fallback to categories list
    }
}

// Handle delete medicine action
if ($action === 'delete_medicine' && $medicine_id > 0) {
    try {
        db_query("DELETE FROM medicines WHERE id = :id", [':id' => $medicine_id]);
        $success = "Medicine deleted successfully";
        // Redirect to medicine list
        header("Location: pharmacy.php");
        exit;
    } catch (PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Handle delete category action
if ($action === 'delete_category' && $category_id > 0) {
    try {
        // Check if category is in use
        $medicines_count = db_get_row("SELECT COUNT(*) as count FROM medicines WHERE category_id = :id", [':id' => $category_id]);
        if ($medicines_count && $medicines_count['count'] > 0) {
            $error = "Cannot delete category: it has associated medicines";
        } else {
            db_query("DELETE FROM medicine_categories WHERE id = :id", [':id' => $category_id]);
            $success = "Category deleted successfully";
        }
        // Redirect to category list
        header("Location: pharmacy.php?action=categories");
        exit;
    } catch (PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Page title based on action
$page_title = "Pharmacy";
if ($action === 'new_medicine') {
    $page_title = "Add New Medicine";
} elseif ($action === 'edit_medicine') {
    $page_title = "Edit Medicine";
} elseif ($action === 'categories') {
    $page_title = "Medicine Categories";
} elseif ($action === 'new_category') {
    $page_title = "Add New Category";
} elseif ($action === 'edit_category') {
    $page_title = "Edit Category";
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
        
        .medicine-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--primary-color);
        }
        
        .medicine-card:hover {
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
        
        .stock-low {
            color: #dc3545;
            font-weight: bold;
        }
        
        .stock-medium {
            color: #fd7e14;
            font-weight: bold;
        }
        
        .stock-high {
            color: #198754;
            font-weight: bold;
        }
        
        .category-badge {
            background-color: var(--secondary-color);
            color: white;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }
        
        .price-tag {
            font-weight: bold;
            color: var(--primary-color);
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
                        <a class="nav-link active" href="pharmacy.php">
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
                    
                    <!-- Different content based on action -->
                    <?php if ($action === 'list'): ?>
                        <!-- Medicines List View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Inventory Management</h5>
                                <div>
                                    <a href="pharmacy.php?action=categories" class="btn btn-outline-secondary btn-sm me-2">
                                        <i class="fas fa-tags me-1"></i> Categories
                                    </a>
                                    <a href="pharmacy.php?action=new_medicine" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i> Add New Medicine
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Category Filter -->
                                <div class="mb-4">
                                    <form class="d-flex align-items-center" method="get">
                                        <label for="categoryFilter" class="me-2">Filter by Category:</label>
                                        <select id="categoryFilter" name="category_id" class="form-select form-select-sm me-2" style="max-width: 200px;">
                                            <option value="0">All Categories</option>
                                            <?php foreach ($medicine_categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                                    </form>
                                </div>
                                
                                <?php if (empty($medicines)): ?>
                                    <div class="alert alert-info">
                                        No medicines found in the inventory.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Category</th>
                                                    <th>Price (₹)</th>
                                                    <th>Stock</th>
                                                    <th>Expiry Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($medicines as $med): ?>
                                                    <tr>
                                                        <td><?php echo $med['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($med['name']); ?></td>
                                                        <td>
                                                            <?php if (!empty($med['category_name'])): ?>
                                                                <span class="category-badge"><?php echo htmlspecialchars($med['category_name']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Uncategorized</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="price-tag">₹<?php echo number_format($med['unit_price'] ?? 0, 2); ?></td>
                                                        <td>
                                                            <?php
                                                            $stock = intval($med['stock_quantity']);
                                                            $stock_class = '';
                                                            if ($stock <= 10) {
                                                                $stock_class = 'stock-low';
                                                            } elseif ($stock <= 30) {
                                                                $stock_class = 'stock-medium';
                                                            } else {
                                                                $stock_class = 'stock-high';
                                                            }
                                                            ?>
                                                            <span class="<?php echo $stock_class; ?>"><?php echo $stock; ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($med['expiry_date'])): ?>
                                                                <?php
                                                                $expiry = new DateTime($med['expiry_date']);
                                                                $today = new DateTime();
                                                                $diff = $today->diff($expiry);
                                                                $days_to_expiry = $expiry > $today ? $diff->days : -$diff->days;
                                                                
                                                                if ($days_to_expiry < 0) {
                                                                    echo '<span class="text-danger">Expired</span>';
                                                                } elseif ($days_to_expiry <= 30) {
                                                                    echo '<span class="text-warning">'.date('Y-m-d', strtotime($med['expiry_date'])).'</span>';
                                                                } else {
                                                                    echo date('Y-m-d', strtotime($med['expiry_date']));
                                                                }
                                                                ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="pharmacy.php?action=edit_medicine&id=<?php echo $med['id']; ?>" class="btn btn-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="confirmDeleteMedicine(<?php echo $med['id']; ?>)" class="btn btn-danger">
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
                    <?php elseif ($action === 'categories'): ?>
                        <!-- Categories List View -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Medicine Categories</h5>
                                <div>
                                    <a href="pharmacy.php" class="btn btn-outline-secondary btn-sm me-2">
                                        <i class="fas fa-pills me-1"></i> Medicines
                                    </a>
                                    <a href="pharmacy.php?action=new_category" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i> Add New Category
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($medicine_categories)): ?>
                                    <div class="alert alert-info">
                                        No medicine categories found.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Description</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($medicine_categories as $cat): ?>
                                                    <tr>
                                                        <td><?php echo $cat['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($cat['description'] ?? ''); ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="pharmacy.php?action=edit_category&category_id=<?php echo $cat['id']; ?>" class="btn btn-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="confirmDeleteCategory(<?php echo $cat['id']; ?>)" class="btn btn-danger">
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
                    <?php elseif ($action === 'new_medicine' || $action === 'edit_medicine'): ?>
                        <!-- Add/Edit Medicine Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo $action === 'new_medicine' ? 'Add New Medicine' : 'Edit Medicine'; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="<?php echo $action === 'edit_medicine' ? "pharmacy.php?action=edit_medicine&id={$medicine_id}" : "pharmacy.php?action=new_medicine"; ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $medicine['name'] ?? ''; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="category_id" class="form-label">Category</label>
                                            <select class="form-select" id="category_id" name="category_id">
                                                <option value="">Select Category</option>
                                                <?php foreach ($medicine_categories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>" <?php echo (isset($medicine['category_id']) && $medicine['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="price" class="form-label">Price (₹) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo $medicine['unit_price'] ?? '0.00'; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="stock_quantity" class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                            <input type="number" step="1" min="0" class="form-control" id="stock_quantity" name="stock_quantity" value="<?php echo $medicine['stock_quantity'] ?? '0'; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="manufacturer" class="form-label">Manufacturer</label>
                                            <input type="text" class="form-control" id="manufacturer" name="manufacturer" value="<?php echo $medicine['manufacturer'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="expiry_date" class="form-label">Expiry Date</label>
                                            <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo $medicine['expiry_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo $medicine['description'] ?? ''; ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <a href="pharmacy.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="save_medicine" class="btn btn-primary">Save Medicine</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($action === 'new_category' || $action === 'edit_category'): ?>
                        <!-- Add/Edit Category Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo $action === 'new_category' ? 'Add New Category' : 'Edit Category'; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="<?php echo $action === 'edit_category' ? "pharmacy.php?action=edit_category&category_id={$category_id}" : "pharmacy.php?action=new_category"; ?>">
                                    <?php if ($action === 'edit_category'): ?>
                                        <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $category['name'] ?? ''; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo $category['description'] ?? ''; ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <a href="pharmacy.php?action=categories" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="save_category" class="btn btn-primary">Save Category</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for confirmation dialogs -->
    <script>
        function confirmDeleteMedicine(id) {
            if (confirm('Are you sure you want to delete this medicine? This action cannot be undone.')) {
                window.location.href = 'pharmacy.php?action=delete_medicine&id=' + id;
            }
        }
        
        function confirmDeleteCategory(id) {
            if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
                window.location.href = 'pharmacy.php?action=delete_category&category_id=' + id;
            }
        }
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>