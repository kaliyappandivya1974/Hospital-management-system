<?php
/**
 * AKIRA HOSPITAL Management System
 * File Synchronization Helper
 * 
 * This file helps to download and package updated files
 * for use in XAMPP environment
 */

// Security check: Only allow this to run on Replit or localhost
$allowed_hosts = ['localhost', '127.0.0.1'];
$replit_domain = isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'replit.dev') !== false;

if (!$replit_domain && !in_array($_SERVER['SERVER_ADDR'] ?? '', $allowed_hosts)) {
    die('This tool can only be run on Replit or localhost for security reasons.');
}

// List of important files for the system
$core_files = [
    'db_connect.php',
    'xampp_sync.php',
    'db_connect_helper.php',
    'billing.php',
    'index.php',
    'login.php',
    'logout.php',
    'dashboard.php',
    'patients.php',
    'doctors.php',
    'appointments.php',
    'laboratory.php',
    'pharmacy.php',
    'reports.php',
    'settings.php',
    'xampp_deployment_guide.php',
    'billing_fix_for_xampp.php',
    'XAMPP_INSTRUCTIONS.md'
];

// Important directories
$core_directories = [
    'includes',
    'css',
    'js',
    'assets'
];

// Function to create a ZIP archive of files
function create_zip($files, $directories, $zip_file) {
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }
    
    // Add individual files
    foreach ($files as $file) {
        if (file_exists($file)) {
            $zip->addFile($file, $file);
        }
    }
    
    // Add directories recursively
    foreach ($directories as $directory) {
        if (is_dir($directory)) {
            $dir_iterator = new RecursiveDirectoryIterator($directory);
            $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
            
            foreach ($iterator as $path => $fileInfo) {
                // Skip dot files like . and ..
                if ($fileInfo->isFile() && substr($fileInfo->getFilename(), 0, 1) !== '.') {
                    $zip->addFile($path, $path);
                } elseif ($fileInfo->isDir() && substr($fileInfo->getFilename(), 0, 1) !== '.') {
                    $zip->addEmptyDir($path);
                }
            }
        }
    }
    
    return $zip->close();
}

// Function to convert bytes to human-readable format
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Start HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKIRA HOSPITAL - Download Updates</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #483D8B;
            padding-bottom: 10px;
            border-bottom: 2px solid #6A5ACD;
        }
        .success {
            background: #E6FFE6;
            border-left: 5px solid #4CAF50;
            padding: 10px;
            margin: 10px 0;
        }
        .warning {
            background: #FFFFCC;
            border-left: 5px solid #FFC107;
            padding: 10px;
            margin: 10px 0;
        }
        .error {
            background: #FFEBEE;
            border-left: 5px solid #F44336;
            padding: 10px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #6A5ACD;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px 0;
            cursor: pointer;
            border: none;
        }
        .btn:hover {
            background: #483D8B;
        }
        .file-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
        }
        .file-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .file-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>AKIRA HOSPITAL Management System</h1>
        <h2>Download Updates for XAMPP</h2>
        
        <div class="description">
            <p>This tool helps you create a ZIP file of the current AKIRA HOSPITAL system files for deployment to your XAMPP environment.</p>
            <p>The ZIP file will include all necessary files for the system to work properly in XAMPP.</p>
        </div>
        
        <div class="file-selection">
            <h3>Files to Include</h3>
            
            <form method="post" action="">
                <div class="file-list">
                    <?php
                    // List core files with checkboxes
                    foreach ($core_files as $file) {
                        $checked = 'checked';
                        $exists = file_exists($file);
                        $file_size = $exists ? format_bytes(filesize($file)) : 'N/A';
                        $label_class = !$exists ? 'style="color:#999;"' : '';
                        
                        echo '<div class="file-item">';
                        echo '<label ' . $label_class . '>';
                        echo '<input type="checkbox" name="files[]" value="' . $file . '" ' . $checked . ' ' . (!$exists ? 'disabled' : '') . '>';
                        echo $file . ($exists ? '' : ' (not found)');
                        echo '</label>';
                        echo '<span>' . $file_size . '</span>';
                        echo '</div>';
                    }
                    
                    // List core directories with checkboxes
                    foreach ($core_directories as $dir) {
                        $checked = 'checked';
                        $exists = is_dir($dir);
                        $label_class = !$exists ? 'style="color:#999;"' : '';
                        
                        echo '<div class="file-item">';
                        echo '<label ' . $label_class . '>';
                        echo '<input type="checkbox" name="directories[]" value="' . $dir . '" ' . $checked . ' ' . (!$exists ? 'disabled' : '') . '>';
                        echo $dir . '/ ' . ($exists ? '' : ' (directory not found)');
                        echo '</label>';
                        echo '<span>' . ($exists ? 'Directory' : 'N/A') . '</span>';
                        echo '</div>';
                    }
                    ?>
                    
                    <!-- Add option for custom files -->
                    <div class="file-item">
                        <label>
                            <input type="checkbox" name="include_custom" value="1">
                            Include custom files/directories (comma-separated)
                        </label>
                    </div>
                </div>
                
                <div id="custom-files" style="display:none; margin-top: 10px;">
                    <label for="custom_files">Custom Files/Directories (comma-separated):</label><br>
                    <input type="text" name="custom_files" id="custom_files" style="width:100%; padding:5px;" placeholder="e.g., custom.php, uploads/, config.ini">
                </div>
                
                <script>
                    // Show/hide custom files input
                    document.querySelector('input[name="include_custom"]').addEventListener('change', function() {
                        document.getElementById('custom-files').style.display = this.checked ? 'block' : 'none';
                    });
                </script>
                
                <div style="margin-top: 20px;">
                    <input type="hidden" name="action" value="create_zip">
                    <button type="submit" class="btn">Create ZIP File</button>
                </div>
            </form>
        </div>
        
        <?php
        // Process ZIP creation request
        if (isset($_POST['action']) && $_POST['action'] == 'create_zip') {
            // Get selected files
            $selected_files = isset($_POST['files']) ? $_POST['files'] : [];
            $selected_dirs = isset($_POST['directories']) ? $_POST['directories'] : [];
            
            // Add custom files/directories if specified
            if (isset($_POST['include_custom']) && $_POST['include_custom'] == '1' && !empty($_POST['custom_files'])) {
                $custom_items = explode(',', $_POST['custom_files']);
                foreach ($custom_items as $item) {
                    $item = trim($item);
                    if (!empty($item)) {
                        if (substr($item, -1) == '/') {
                            // It's a directory
                            $item = rtrim($item, '/');
                            if (is_dir($item) && !in_array($item, $selected_dirs)) {
                                $selected_dirs[] = $item;
                            }
                        } else {
                            // It's a file
                            if (file_exists($item) && !in_array($item, $selected_files)) {
                                $selected_files[] = $item;
                            }
                        }
                    }
                }
            }
            
            // Generate a unique ZIP filename
            $zip_file = 'akira_hospital_' . date('Ymd_His') . '.zip';
            
            // Create ZIP archive
            if (create_zip($selected_files, $selected_dirs, $zip_file)) {
                $zip_size = format_bytes(filesize($zip_file));
                
                echo '<div class="success">';
                echo "ZIP file created successfully: $zip_file ($zip_size)";
                echo '</div>';
                
                echo '<div style="margin-top: 20px;">';
                echo '<a href="' . $zip_file . '" class="btn" download>Download ZIP</a>';
                echo '</div>';
                
                echo '<div class="warning" style="margin-top: 20px;">';
                echo '<h4>XAMPP Installation Instructions:</h4>';
                echo '<ol>';
                echo '<li>Download the ZIP file to your computer</li>';
                echo '<li>Unzip the contents into your XAMPP htdocs directory (e.g., C:\xampp\htdocs\akira-hospital\)</li>';
                echo '<li>Open your browser and navigate to http://localhost/akira-hospital/ (or your specific path)</li>';
                echo '<li>Run the xampp_deployment_guide.php file first to check for any issues</li>';
                echo '<li>If you encounter billing module issues, run billing_fix_for_xampp.php</li>';
                echo '</ol>';
                echo '</div>';
            } else {
                echo '<div class="error">Failed to create ZIP file. Please check file permissions.</div>';
            }
        }
        ?>
        
        <div class="navigation" style="margin-top: 30px;">
            <h3>Navigation</h3>
            <a href="index.php" class="btn">Return to Homepage</a>
            <a href="xampp_deployment_guide.php" class="btn">XAMPP Deployment Guide</a>
            <a href="billing_fix_for_xampp.php" class="btn">Billing Fix for XAMPP</a>
        </div>
    </div>
</body>
</html>