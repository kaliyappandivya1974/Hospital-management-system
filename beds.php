<?php
/**
 * AKIRA HOSPITAL Management System
 * Beds Management Page - Default Version
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

// Define bed types with their total counts
$bed_types = [
    'general' => ['name' => 'General Ward', 'total' => 150],
    'semi_private' => ['name' => 'Semi-Private Room', 'total' => 50],
    'private' => ['name' => 'Private Room', 'total' => 50],
    'icu' => ['name' => 'ICU', 'total' => 20],
    'nicu' => ['name' => 'NICU', 'total' => 10],
    'emergency' => ['name' => 'Emergency', 'total' => 10],
    'operation' => ['name' => 'Operation Theatre', 'total' => 10]
];

// Total beds: 300

// Define default bed statuses
$default_beds = [
    // General Ward - 150 beds
    ['type' => 'general', 'occupied' => 95, 'available' => 45, 'maintenance' => 5, 'reserved' => 5],
    
    // Semi-Private - 50 beds
    ['type' => 'semi_private', 'occupied' => 30, 'available' => 15, 'maintenance' => 2, 'reserved' => 3],
    
    // Private - 50 beds
    ['type' => 'private', 'occupied' => 20, 'available' => 25, 'maintenance' => 2, 'reserved' => 3],
    
    // ICU - 20 beds
    ['type' => 'icu', 'occupied' => 15, 'available' => 3, 'maintenance' => 1, 'reserved' => 1],
    
    // NICU - 10 beds
    ['type' => 'nicu', 'occupied' => 7, 'available' => 2, 'maintenance' => 1, 'reserved' => 0],
    
    // Emergency - 10 beds
    ['type' => 'emergency', 'occupied' => 6, 'available' => 3, 'maintenance' => 1, 'reserved' => 0],
    
    // Operation Theatre - 10 beds
    ['type' => 'operation', 'occupied' => 4, 'available' => 5, 'maintenance' => 1, 'reserved' => 0]
];

// Calculate total statistics
$total_beds_capacity = 0;
$total_occupied = 0;
$total_available = 0;
$total_maintenance = 0;
$total_reserved = 0;

foreach ($default_beds as $bed_type) {
    $type_key = $bed_type['type'];
    $total_beds_capacity += $bed_types[$type_key]['total'];
    $total_occupied += $bed_type['occupied'];
    $total_available += $bed_type['available'];
    $total_maintenance += $bed_type['maintenance'];
    $total_reserved += $bed_type['reserved'];
}

// Create a sample list of beds for display
$sample_beds = [];
$ward_names = [
    'general' => ['A', 'B', 'C', 'D'],
    'semi_private' => ['SP'],
    'private' => ['P'],
    'icu' => ['ICU'],
    'nicu' => ['NICU'],
    'emergency' => ['ER'],
    'operation' => ['OT']
];

$bed_count = 1;
foreach ($default_beds as $bed_type) {
    $type_key = $bed_type['type'];
    $wards = $ward_names[$type_key];
    
    $occupied_count = $bed_type['occupied'];
    $available_count = $bed_type['available'];
    $maintenance_count = $bed_type['maintenance'];
    $reserved_count = $bed_type['reserved'];
    
    foreach ($wards as $ward) {
        $beds_per_ward = ceil($bed_types[$type_key]['total'] / count($wards));
        
        for ($i = 1; $i <= $beds_per_ward; $i++) {
            $status = 'available';
            $patient_name = null;
            
            // Assign status based on counts
            if ($occupied_count > 0) {
                $status = 'occupied';
                $occupied_count--;
                $patient_name = getRandomPatientName();
            } elseif ($maintenance_count > 0) {
                $status = 'maintenance';
                $maintenance_count--;
            } elseif ($reserved_count > 0) {
                $status = 'reserved';
                $reserved_count--;
            } elseif ($available_count > 0) {
                $status = 'available';
                $available_count--;
            }
            
            // Add to sample beds list
            if ($bed_count <= 20) {  // Only show 20 beds in the table
                $sample_beds[] = [
                    'id' => $bed_count,
                    'bed_number' => $ward . '-' . sprintf("%03d", $i),
                    'ward' => $ward,
                    'type' => $type_key,
                    'status' => $status,
                    'patient_name' => $patient_name
                ];
            }
            
            $bed_count++;
        }
    }
}

// Function to generate random patient names
function getRandomPatientName() {
    $first_names = ['John', 'Jane', 'Robert', 'Mary', 'Michael', 'Sarah', 'David', 'Lisa', 'William', 'Emily'];
    $last_names = ['Smith', 'Johnson', 'Williams', 'Jones', 'Brown', 'Davis', 'Miller', 'Wilson', 'Taylor', 'Clark'];
    
    return $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
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
                <h1 class="h2">Bed Management</h1>
                <div class="user-welcome">
                    Welcome, <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                    <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($admin_role); ?></span>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Total Beds</div>
                                    <div class="text-lg fw-bold"><?php echo $total_beds_capacity; ?></div>
                                </div>
                                <i class="fas fa-bed fa-2x text-white-50"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <span class="small text-white stretched-link">Hospital Capacity</span>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Available Beds</div>
                                    <div class="text-lg fw-bold"><?php echo $total_available; ?></div>
                                </div>
                                <i class="fas fa-check-circle fa-2x text-white-50"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <span class="small text-white stretched-link">Ready for Admission</span>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Occupied Beds</div>
                                    <div class="text-lg fw-bold"><?php echo $total_occupied; ?></div>
                                </div>
                                <i class="fas fa-procedures fa-2x text-white-50"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <span class="small text-white stretched-link">Currently In Use</span>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Unavailable</div>
                                    <div class="text-lg fw-bold"><?php echo ($total_maintenance + $total_reserved); ?></div>
                                </div>
                                <i class="fas fa-tools fa-2x text-white-50"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <span class="small text-white stretched-link">Under Maintenance/Reserved</span>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Beds by Type Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Beds by Type</h6>
                    <button class="btn btn-primary btn-sm" disabled>
                        <i class="fas fa-plus"></i> Add New Bed
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="bedsTypeTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Bed Type</th>
                                    <th>Total Capacity</th>
                                    <th>Available</th>
                                    <th>Occupied</th>
                                    <th>Under Maintenance</th>
                                    <th>Reserved</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($default_beds as $bed_type): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bed_types[$bed_type['type']]['name']); ?></td>
                                        <td><?php echo $bed_types[$bed_type['type']]['total']; ?></td>
                                        <td><?php echo $bed_type['available']; ?></td>
                                        <td><?php echo $bed_type['occupied']; ?></td>
                                        <td><?php echo $bed_type['maintenance']; ?></td>
                                        <td><?php echo $bed_type['reserved']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td><?php echo $total_beds_capacity; ?></td>
                                    <td><?php echo $total_available; ?></td>
                                    <td><?php echo $total_occupied; ?></td>
                                    <td><?php echo $total_maintenance; ?></td>
                                    <td><?php echo $total_reserved; ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Beds List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Sample Bed Listing (20 of <?php echo $total_beds_capacity; ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This is a static default version of the bed management page. 
                        The full functionality is currently being developed.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="bedsDataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Bed Number</th>
                                    <th>Ward</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Patient</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sample_beds as $bed): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bed['bed_number']); ?></td>
                                        <td><?php echo htmlspecialchars($bed['ward']); ?></td>
                                        <td><?php echo htmlspecialchars($bed_types[$bed['type']]['name']); ?></td>
                                        <td>
                                            <?php
                                            $status_class = 'badge-secondary';
                                            $status_text = ucfirst($bed['status']);
                                            
                                            if ($bed['status'] === 'available') {
                                                $status_class = 'badge-success';
                                            } elseif ($bed['status'] === 'occupied') {
                                                $status_class = 'badge-warning';
                                            } elseif ($bed['status'] === 'maintenance') {
                                                $status_class = 'badge-danger';
                                            } elseif ($bed['status'] === 'reserved') {
                                                $status_class = 'badge-info';
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td>
                                            <?php echo $bed['patient_name'] ? htmlspecialchars($bed['patient_name']) : 'N/A'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Bed Occupancy Chart -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Bed Occupancy Overview</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="bedOccupancyChart"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#bedsDataTable').DataTable();
        
        // Initialize Chart
        const ctx = document.getElementById('bedOccupancyChart').getContext('2d');
        const bedOccupancyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    foreach ($default_beds as $bed_type) {
                        echo "'" . $bed_types[$bed_type['type']]['name'] . "', ";
                    }
                    ?>
                ],
                datasets: [
                    {
                        label: 'Available',
                        data: [
                            <?php 
                            foreach ($default_beds as $bed_type) {
                                echo $bed_type['available'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(40, 167, 69, 0.8)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Occupied',
                        data: [
                            <?php 
                            foreach ($default_beds as $bed_type) {
                                echo $bed_type['occupied'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(255, 193, 7, 0.8)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Maintenance',
                        data: [
                            <?php 
                            foreach ($default_beds as $bed_type) {
                                echo $bed_type['maintenance'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(220, 53, 69, 0.8)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Reserved',
                        data: [
                            <?php 
                            foreach ($default_beds as $bed_type) {
                                echo $bed_type['reserved'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(23, 162, 184, 0.8)',
                        borderColor: 'rgba(23, 162, 184, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
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

<?php include_once 'includes/footer.php'; ?>