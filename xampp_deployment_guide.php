<?php
/**
 * AKIRA HOSPITAL Management System
 * XAMPP Deployment Helper
 *
 * This script helps diagnose common XAMPP deployment issues 
 * and provides compatibility checks for the system.
 */

// Enable error reporting for better debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKIRA HOSPITAL - XAMPP Deployment Guide</title>
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
        h2 {
            color: #6A5ACD;
            margin-top: 20px;
        }
        .step {
            background: white;
            padding: 15px;
            margin: 15px 0;
            border-left: 5px solid #6A5ACD;
            border-radius: 3px;
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
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        code {
            font-family: Consolas, monospace;
            background: #f0f0f0;
            padding: 2px 4px;
            border-radius: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #6A5ACD;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px 0;
        }
        .btn:hover {
            background: #483D8B;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>AKIRA HOSPITAL Management System</h1>
        <h2>XAMPP Deployment Guide</h2>
        
        <div class="step">
            <h3>Step 1: XAMPP Environment Check</h3>
            <?php
            // Check if running in XAMPP
            $is_xampp = false;
            if (stripos($_SERVER['SERVER_SOFTWARE'], 'xampp') !== false) {
                $is_xampp = true;
                echo "<div class='success'>Running on XAMPP server ✓</div>";
            } else {
                echo "<div class='warning'>Not running on XAMPP. This script works best when run from XAMPP.</div>";
            }
            
            // Check PHP version
            $php_version = phpversion();
            $required_version = '7.4.0';
            if (version_compare($php_version, $required_version, '>=')) {
                echo "<div class='success'>PHP Version: $php_version ✓</div>";
            } else {
                echo "<div class='error'>PHP Version: $php_version - Please upgrade to at least $required_version.</div>";
            }
            
            // Check required extensions
            $required_extensions = [
                'pdo',
                'pdo_mysql',
                'json',
                'mbstring',
                'dom',
                'session'
            ];
            
            echo "<h4>PHP Extensions:</h4>";
            echo "<ul>";
            foreach ($required_extensions as $ext) {
                if (extension_loaded($ext)) {
                    echo "<li>$ext <span style='color:green'>✓</span></li>";
                } else {
                    echo "<li>$ext <span style='color:red'>✗</span> - This extension is required.</li>";
                }
            }
            echo "</ul>";
            
            // Check if pdo_pgsql is available
            $has_pgsql = extension_loaded('pdo_pgsql');
            if ($has_pgsql) {
                echo "<div class='success'>PostgreSQL support available ✓</div>";
            } else {
                echo "<div class='warning'>PostgreSQL support not available. Only MySQL will be used for database connections.</div>";
            }
            ?>
        </div>
        
        <div class="step">
            <h3>Step 2: Database Connection</h3>
            <?php
            // Include database connection
            $db_connected = false;
            $db_type = null;
            $db_error = null;
            
            try {
                // Use include_once to prevent errors if db_connect.php defines functions or variables
                include_once 'db_connect.php';
                
                // Check global connection variables from db_connect.php
                if (isset($pdo) && $pdo instanceof PDO) {
                    $db_connected = true;
                    $db_type = isset($active_db_type) ? $active_db_type : 'unknown';
                    
                    echo "<div class='success'>Database connection successful ✓<br>
                          Database type: " . ucfirst($db_type) . "</div>";
                    
                    // Check if tables exist
                    $tables_to_check = ['users', 'patients', 'doctors', 'appointments', 'invoices'];
                    $missing_tables = [];
                    
                    foreach ($tables_to_check as $table) {
                        $sql = ($db_type == 'postgres') ?
                            "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = '$table')" :
                            "SHOW TABLES LIKE '$table'";
                        
                        $result = $pdo->query($sql);
                        
                        if ($db_type == 'postgres') {
                            $exists = $result->fetchColumn();
                            if (!$exists) {
                                $missing_tables[] = $table;
                            }
                        } else { // MySQL
                            if ($result->rowCount() == 0) {
                                $missing_tables[] = $table;
                            }
                        }
                    }
                    
                    if (empty($missing_tables)) {
                        echo "<div class='success'>All required database tables exist ✓</div>";
                    } else {
                        echo "<div class='warning'>Some tables are missing: " . implode(', ', $missing_tables) . "<br>
                              These will be created automatically when you first access the system.</div>";
                    }
                    
                    // Check for default admin user
                    $admin_check_sql = "SELECT * FROM users WHERE username = 'admin'";
                    $admin_result = $pdo->query($admin_check_sql);
                    
                    if ($admin_result && $admin_result->rowCount() > 0) {
                        echo "<div class='success'>Default admin user exists ✓</div>";
                    } else {
                        echo "<div class='warning'>Default admin user not found. It will be created when you first access the system.</div>";
                    }
                    
                } else {
                    echo "<div class='error'>Database connection not established. Check your db_connect.php file.</div>";
                }
            } catch (Throwable $e) {
                $db_error = $e->getMessage();
                echo "<div class='error'>Database connection error: $db_error</div>";
            }
            ?>
            
            <h4>Database Configuration</h4>
            <pre>
<?php
// Show database configuration (without passwords)
if (isset($db_host)) {
    echo "Host: $db_host\n";
}
if (isset($db_name)) {
    echo "Database: $db_name\n";
}
if (isset($db_type)) {
    echo "Database Type: $db_type\n";
}
if (isset($pg_port) && $has_pgsql) {
    echo "PostgreSQL Port: $pg_port\n";
}
if (isset($mysql_port)) {
    echo "MySQL Port: $mysql_port\n";
}
?>
            </pre>
        </div>
        
        <div class="step">
            <h3>Step 3: File System Check</h3>
            <?php
            // Check for required files
            $required_files = [
                'db_connect.php',
                'xampp_sync.php',
                'index.php',
                'login.php',
                'dashboard.php',
                'patients.php',
                'doctors.php',
                'appointments.php',
                'billing.php',
                'includes/header.php',
                'includes/navbar.php',
                'includes/footer.php',
                'css/styles.css',
                'js/script.js'
            ];
            
            $missing_files = [];
            foreach ($required_files as $file) {
                if (!file_exists($file)) {
                    $missing_files[] = $file;
                }
            }
            
            if (empty($missing_files)) {
                echo "<div class='success'>All required files are present ✓</div>";
            } else {
                echo "<div class='error'>Missing files: <ul>";
                foreach ($missing_files as $file) {
                    echo "<li>$file</li>";
                }
                echo "</ul></div>";
            }
            
            // Check file permissions
            $writable_dirs = [
                '.',
                'includes',
                'css',
                'js',
                'uploads'
            ];
            
            echo "<h4>Directory Permissions:</h4>";
            foreach ($writable_dirs as $dir) {
                if (file_exists($dir)) {
                    if (is_writable($dir)) {
                        echo "<div>$dir: <span style='color:green'>Writable ✓</span></div>";
                    } else {
                        echo "<div>$dir: <span style='color:red'>Not writable ✗</span> - This directory needs write permissions.</div>";
                    }
                } else {
                    if ($dir != 'uploads') { // uploads is optional
                        echo "<div>$dir: <span style='color:red'>Not found ✗</span></div>";
                    } else {
                        echo "<div>$dir: <span style='color:orange'>Not found (optional directory)</span></div>";
                    }
                }
            }
            ?>
            
            <h4>Helper Files Status:</h4>
            <ul>
                <?php
                // Check xampp_sync.php
                if (file_exists('xampp_sync.php')) {
                    echo "<li>xampp_sync.php: <span style='color:green'>Present ✓</span></li>";
                    if (function_exists('pdo_query')) {
                        echo "<li>PDO helper functions: <span style='color:green'>Available ✓</span></li>";
                    } else {
                        echo "<li>PDO helper functions: <span style='color:orange'>Not loaded</span> - Include xampp_sync.php in your PHP files.</li>";
                    }
                } else {
                    echo "<li>xampp_sync.php: <span style='color:red'>Missing ✗</span> - This file is needed for XAMPP compatibility.</li>";
                }
                
                // Check db_connect_helper.php
                if (file_exists('db_connect_helper.php')) {
                    echo "<li>db_connect_helper.php: <span style='color:green'>Present ✓</span></li>";
                } else {
                    echo "<li>db_connect_helper.php: <span style='color:red'>Missing ✗</span> - This file provides fallback database functions.</li>";
                }
                ?>
            </ul>
        </div>
        
        <div class="step">
            <h3>Step 4: XAMPP/MySQL Compatibility Check</h3>
            <?php
            // Check if MySQL is the active database type
            if (isset($active_db_type) && $active_db_type == 'mysql') {
                echo "<div class='success'>Using MySQL database ✓ - Compatible with XAMPP</div>";
            } elseif (isset($active_db_type) && $active_db_type == 'postgres') {
                echo "<div class='warning'>Using PostgreSQL database - This requires the pdo_pgsql extension in XAMPP.</div>";
                echo "<div>To switch to MySQL, change <code>\$db_type = 'mysql';</code> in db_connect.php</div>";
            } else {
                echo "<div class='warning'>Database type not determined. Check your db_connect.php file.</div>";
            }
            
            // Check if RETURNING syntax is used in SQL (not compatible with MySQL)
            $files_to_check = [
                'db_connect.php',
                'billing.php',
                'patients.php',
                'doctors.php',
                'appointments.php'
            ];
            
            $files_with_returning = [];
            foreach ($files_to_check as $file) {
                if (file_exists($file)) {
                    $content = file_get_contents($file);
                    if (stripos($content, 'RETURNING') !== false) {
                        $files_with_returning[] = $file;
                    }
                }
            }
            
            if (!empty($files_with_returning)) {
                echo "<div class='warning'>Found PostgreSQL 'RETURNING' syntax in these files, which may not work with MySQL:";
                echo "<ul>";
                foreach ($files_with_returning as $file) {
                    echo "<li>$file</li>";
                }
                echo "</ul>";
                echo "Use <code>lastInsertId()</code> for getting IDs in MySQL instead.</div>";
            } else {
                echo "<div class='success'>No incompatible SQL syntax detected ✓</div>";
            }
            ?>
        </div>
        
        <div class="step">
            <h3>Step 5: Fixing Common Issues</h3>
            
            <h4>Issue 1: Changes in Replit not showing in XAMPP</h4>
            <p>Solution:</p>
            <ol>
                <li>Make sure you've downloaded the latest files from Replit to your XAMPP directory</li>
                <li>Clear your browser cache (Ctrl+F5 or Cmd+Shift+R)</li>
                <li>Restart Apache and MySQL in XAMPP Control Panel</li>
            </ol>
            
            <h4>Issue 2: Database Connection Errors</h4>
            <p>Solution:</p>
            <ol>
                <li>Verify MySQL service is running in XAMPP</li>
                <li>Check database name and credentials in db_connect.php</li>
                <li>Make sure the database exists in phpMyAdmin</li>
            </ol>
            
            <h4>Issue 3: Invoice Creation Fails</h4>
            <p>Solution:</p>
            <ol>
                <li>Make sure you're using the PDO-specific functions from xampp_sync.php</li>
                <li>Include <code>require_once 'xampp_sync.php';</code> at the top of billing.php</li>
                <li>Use <code>create_invoice_pdo()</code> function for creating invoices in XAMPP</li>
                <li>Use direct PDO statements instead of PostgreSQL-specific functions</li>
            </ol>
            
            <h4>Issue 4: File Not Found/404 Errors</h4>
            <p>Solution:</p>
            <ol>
                <li>Check file paths and permissions</li>
                <li>Make sure all files from Replit are copied to the correct location in XAMPP</li>
                <li>Verify .htaccess configuration (if used)</li>
            </ol>
        </div>
        
        <div class="step">
            <h3>Step 6: Next Steps</h3>
            
            <p>Now that you have verified your XAMPP setup, you can:</p>
            
            <div>
                <a href="index.php" class="btn">Go to Homepage</a>
                <a href="login.php" class="btn">Login to System</a>
            </div>
            
            <p>Default login credentials:</p>
            <ul>
                <li>Username: <code>admin</code></li>
                <li>Password: <code>admin123</code></li>
            </ul>
            
            <p>For more information, refer to:</p>
            <ul>
                <li><a href="XAMPP_INSTRUCTIONS.md">XAMPP Deployment Instructions</a></li>
            </ul>
        </div>
    </div>
</body>
</html>