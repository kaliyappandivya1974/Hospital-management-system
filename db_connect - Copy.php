<?php
/**
 * AKIRA HOSPITAL Management System
 * Database Connection for XAMPP with PostgreSQL/MySQL Fallback
 */

// Enable error reporting for debugging (comment out in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include helper files for XAMPP compatibility if needed
$helper_file = __DIR__ . '/db_connect_helper.php';
if (file_exists($helper_file)) {
    include_once $helper_file;
}

// Database type - can be 'postgres' or 'mysql'
$db_type = 'mysql';  // Changed to use MySQL with XAMPP

// Database configuration - Edit these values to match your XAMPP setup
$db_host = "localhost";
$db_name = "akira_hospital"; // Changed to match the actual database name in phpMyAdmin

// PostgreSQL specific settings
$pg_port = "5432";
$pg_user = "postgres";
$pg_password = "postgres"; // Change this to your PostgreSQL password

// MySQL specific settings
$mysql_port = "3306";
$mysql_user = "root";
$mysql_password = ""; // Default XAMPP MySQL password is blank

// Store original error handler
$pg_error = null;

// Read from environment variables if available (for production environments)
if ($db_type == 'postgres') {
    // Check if we have PostgreSQL environment variables 
    $check_vars = ['PGHOST', 'PGPORT', 'PGDATABASE', 'PGUSER', 'PGPASSWORD'];
    $all_vars_present = true;
    foreach ($check_vars as $var) {
        if (empty(getenv($var))) {
            $all_vars_present = false;
            error_log("Environment variable {$var} is not set");
        }
    }
    
    // If all variables are present, use them
    if ($all_vars_present) {
        error_log("Using PostgreSQL environment variables");
        $db_host = getenv('PGHOST');
        $pg_port = getenv('PGPORT');
        $db_name = getenv('PGDATABASE');
        $pg_user = getenv('PGUSER');
        $pg_password = getenv('PGPASSWORD');
    } else {
        error_log("Not all PostgreSQL environment variables are set. Using defaults.");
        // Just use the default values defined above
    }
}

// Global PDO object
$pdo = null;
$db_connected = false;
$active_db_type = null;

// Try PostgreSQL first (if selected)
if ($db_type == 'postgres') {
    try {
        // Check if PostgreSQL PDO driver is available
        if (!extension_loaded('pdo_pgsql')) {
            throw new PDOException("PostgreSQL PDO driver not available");
        }
        
        // Connection string for PostgreSQL
        $connection_string = "pgsql:host={$db_host};port={$pg_port};dbname={$db_name}";
        
        // Create a PDO instance for PostgreSQL
        $pdo = new PDO($connection_string, $pg_user, $pg_password);
        
        // Set error mode to exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set default fetch mode to associative array
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Use prepared statements for security
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        $db_connected = true;
        $active_db_type = 'postgres';
        error_log("PostgreSQL connection established successfully");
    } catch (PDOException $e) {
        // Log the error
        error_log("PostgreSQL connection failed: " . $e->getMessage());
        
        // If PostgreSQL fails, we'll try MySQL next
    }
}

// Try MySQL if PostgreSQL failed or if MySQL was selected
if (!$db_connected) {
    try {
        // Check if MySQL PDO driver is available
        if (!extension_loaded('pdo_mysql')) {
            error_log("MySQL PDO driver not available, skipping MySQL connection attempt");
            throw new PDOException("MySQL PDO driver not available");
        }
        
        // Check if MySQL is running on the specified port
        $connection_test = @fsockopen($db_host, $mysql_port, $errno, $errstr, 1);
        if (!$connection_test) {
            error_log("MySQL not running on port {$mysql_port}, skipping MySQL connection attempt. Error: {$errstr} ({$errno})");
            throw new PDOException("MySQL not running on port {$mysql_port}. Error: {$errstr}");
        }
        fclose($connection_test);
        
        // Connection string for MySQL
        $connection_string = "mysql:host={$db_host};port={$mysql_port};dbname={$db_name}";
        
        // Create a PDO instance for MySQL
        try {
            $pdo = new PDO($connection_string, $mysql_user, $mysql_password, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00';SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';"
            ));
        } catch (PDOException $e) {
            // If the database doesn't exist, try to create it (for MySQL)
            if (strpos($e->getMessage(), "Unknown database") !== false) {
                $temp_connection_string = "mysql:host={$db_host};port={$mysql_port}";
                error_log("Attempting to create database '{$db_name}' in MySQL");
                
                try {
                    $temp_pdo = new PDO($temp_connection_string, $mysql_user, $mysql_password);
                    $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $temp_pdo = null; // Close temporary connection
                    
                    // Try connecting again with the updated settings
                    $pdo = new PDO($connection_string, $mysql_user, $mysql_password, array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00';SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';"
                    ));
                } catch (PDOException $innerEx) {
                    error_log("Failed to create database: " . $innerEx->getMessage());
                    echo "<div style='max-width: 800px; margin: 50px auto; padding: 20px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;'>\n                            <h2 style='margin-top: 0;\'>MySQL Database Creation Error</h2>\n                            <p>Could not create database '{$db_name}'. Please check MySQL permissions.</p>\n                            <p><strong>Error details:</strong> " . htmlspecialchars($innerEx->getMessage()) . "</p>\n                        </div>";
                    die(); // Stop execution as database creation failed
                }
            } else {
                throw $e; // Re-throw other exceptions
            }
        }
        
        $db_connected = true;
        $active_db_type = 'mysql';
        error_log("MySQL connection established successfully");
        
        // Create basic tables if they don't exist (for MySQL)
        createBasicTablesIfNeeded($pdo);
    } catch (PDOException $e) {
        // Log the error
        error_log("MySQL connection failed: " . $e->getMessage());
        echo "<div style='max-width: 800px; margin: 50px auto; padding: 20px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;'>\n                <h2 style='margin-top: 0;\'>MySQL Connection Error</h2>\n                <p>Could not connect to MySQL database.</p>\n                <p>Please ensure MySQL is running and credentials are correct.</p>\n                <p><strong>Error details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n            </div>";
        // Attempt to use SQLite as a last resort for simple operations
        try {
            // Create a SQLite database in memory or file for temporary use
            $sqlite_path = __DIR__ . '/temp_database.sqlite';
            $pdo = new PDO('sqlite:' . $sqlite_path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $db_connected = true;
            $active_db_type = 'sqlite';
            error_log("Fallback to SQLite database for basic functionality");
            
            // Create basic tables for SQLite
            createBasicTablesForSQLite($pdo);
            
            // Display warning but continue
            echo "<div style='background-color: #FFF3CD; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #FAEBCC;'>\n                    <strong>Warning:</strong> Could not connect to primary database. Running in limited functionality mode.<br>\n                    For full functionality, please ensure PostgreSQL or MySQL is running properly.\n                </div>";
            
            return; // Continue with limited functionality
        } catch (PDOException $sqlite_e) {
            error_log("SQLite fallback also failed: " . $sqlite_e->getMessage());
            
            // If all database options fail, provide a comprehensive error message
            $error_message = "\n========================================================================\nDATABASE CONNECTION ERROR\n\nWe tried PostgreSQL, MySQL, and SQLite fallback, but couldn't connect to any database.\n\nPlease check:\n1. Either PostgreSQL or MySQL service is running\n2. For PostgreSQL: \n   - User: {$pg_user}\n   - Database: {$db_name}\n   - Port: {$pg_port}\n3. For MySQL:\n   - User: {$mysql_user}\n   - Database: {$db_name}\n   - Port: {$mysql_port}\n4. Make sure the PHP PDO extensions are enabled in php.ini\n\nPostgreSQL error: " . (isset($pg_error) ? $pg_error : "Not attempted") . "\nMySQL error: {$e->getMessage()}\nSQLite error: {$sqlite_e->getMessage()}\n========================================================================\n                ";
            error_log($error_message);
            
            die("\n                    <div style='max-width: 800px; margin: 50px auto; padding: 20px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;\'>\n                        <h2 style='margin-top: 0;\'>Database Connection Error</h2>\n                        <p>Could not connect to any database. Please check your configuration.</p>\n                        <h3>Things to check:</h3>\n                        <ol>\n                            <li>Make sure either PostgreSQL or MySQL is running</li>\n                            <li>Verify database credentials in the configuration file</li>\n                            <li>Ensure PHP PDO extensions are enabled</li>\n                        </ol>\n                        <p><strong>Error details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n                        <p>For assistance, please contact your system administrator.</p>\n                        <p><a href=\'javascript:history.back()\' style=\'color: #721c24; text-decoration: underline;\'>Go back</a></p>\n                    </div>\n                ");
        }
    }
}

// If $pdo is still null after all attempts, display a fatal error
if ($pdo === null) {
    die("\n            <div style='max-width: 800px; margin: 50px auto; padding: 20px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;\'>\n                <h2 style='margin-top: 0;\'>Fatal Database Connection Error</h2>\n                <p>The application could not establish a database connection.</p>\n                <p>Please check your database server (MySQL/PostgreSQL) is running and accessible.</p>\n                <p>Refer to PHP error logs for more details.</p>\n            </div>\n        ");
}

/**
 * Creates the basic tables needed for the system if they don't exist (MySQL)
 * 
 * @param PDO $pdo The PDO connection
 */
function createBasicTablesIfNeeded($pdo) {
    global $active_db_type;
    
    if ($active_db_type != 'mysql') {
        return; // Only run this for MySQL
    }
    
    try {
        // Create users table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NULL,
                `phone` VARCHAR(20) NULL,
                `role` ENUM('admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician', 'staff') NOT NULL DEFAULT 'staff',
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Check if admin user exists
        $stmt = $pdo->query("SELECT * FROM `users` WHERE `username` = 'admin'");
        $admin = $stmt->fetch();
        
        // Create admin user if it doesn't exist
        if (!$admin) {
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("
                INSERT INTO `users` (`username`, `password`, `name`, `email`, `role`)
                VALUES ('admin', '{$hashedPassword}', 'System Admin', 'admin@akira.hospital', 'admin')
            ");
            error_log("Created default admin user for MySQL");
        }
    } catch (PDOException $e) {
        error_log("Error creating basic tables: " . $e->getMessage());
    }
}

/**
 * Creates the basic tables needed for SQLite fallback
 * 
 * @param PDO $pdo The SQLite PDO connection
 */
function createBasicTablesForSQLite($pdo) {
    try {
        // Create users table for SQLite
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NULL,
                phone TEXT NULL,
                role TEXT NOT NULL DEFAULT 'staff',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Check if admin user exists
        $stmt = $pdo->query("SELECT * FROM users WHERE username = 'admin'");
        $admin = $stmt->fetch();
        
        // Create admin user if it doesn't exist
        if (!$admin) {
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("
                INSERT INTO users (username, password, name, email, role)
                VALUES ('admin', '{$hashedPassword}', 'System Admin', 'admin@akira.hospital', 'admin')
            ");
            error_log("Created default admin user for SQLite");
        }
        
        // Create basic version of doctors table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS doctors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                specialization TEXT NULL,
                phone TEXT NULL,
                email TEXT NULL,
                fee REAL DEFAULT 0
            )
        ");
        
        // Create basic version of patients table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS patients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                medical_record_number TEXT NULL,
                phone TEXT NULL,
                email TEXT NULL,
                address TEXT NULL,
                date_of_birth TEXT NULL
            )
        ");
        
        // Insert some sample doctor data if empty
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM doctors");
        $count = $stmt->fetch();
        if ($count['count'] == 0) {
            $pdo->exec("
                INSERT INTO doctors (name, specialization, fee) VALUES 
                ('Dr. John Smith', 'General Medicine', 500),
                ('Dr. Sarah Johnson', 'Cardiology', 1000)
            ");
        }
        
        // Create basic version of appointments table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id INTEGER,
                doctor_id INTEGER,
                appointment_date TEXT,
                appointment_time TEXT,
                status TEXT DEFAULT 'scheduled',
                notes TEXT NULL,
                FOREIGN KEY (patient_id) REFERENCES patients(id),
                FOREIGN KEY (doctor_id) REFERENCES doctors(id)
            )
        ");
        
    } catch (PDOException $e) {
        error_log("Error creating basic tables for SQLite: " . $e->getMessage());
    }
}

/**
 * Helper function to execute SQL queries and handle errors
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind to the query
 * @return PDOStatement The executed statement
 */
function execute_query($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));
        
        // Re-throw the exception for the calling code to handle
        throw $e;
    }
}

/**
 * Helper function to get a single row from database
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind to the query
 * @return array|null The fetched row or null if not found
 */
function db_get_row($sql, $params = []) {
    try {
        $stmt = execute_query($sql, $params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to get row: " . $e->getMessage());
        return null;
    }
}

/**
 * Helper function to get multiple rows from database
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind to the query
 * @return array The fetched rows
 */
function db_get_rows($sql, $params = []) {
    try {
        $stmt = execute_query($sql, $params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get rows: " . $e->getMessage());
        return [];
    }
}

/**
 * Helper function to insert a record and get the inserted ID
 * 
 * @param string $table The table to insert into
 * @param array $data Associative array of column => value
 * @return int|null The last inserted ID or null on failure
 */
function db_insert($table, $data) {
    global $pdo, $active_db_type;
    
    try {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        // Prepare SQL statement based on database type
        if ($active_db_type === 'postgres') {
            $sql = "INSERT INTO " . $table . " (" . implode(', ', $columns) . ") 
                   VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
                   
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            
            return $stmt->fetchColumn();
        } else if ($active_db_type === 'sqlite') {
            // SQLite implementation
            // Check if we need to quote the table name for SQLite
            $table = str_replace('`', '', $table); // Remove backticks if present
            
            $sql = "INSERT INTO " . $table . " (" . implode(', ', $columns) . ") 
                   VALUES (" . implode(', ', $placeholders) . ")";
                   
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute(array_values($data));
            
            if ($result) {
                return $pdo->lastInsertId();
            } else {
                return false;
            }
        } else {
            // MySQL implementation
            $sql = "INSERT INTO " . $table . " (" . implode(', ', $columns) . ") 
                   VALUES (" . implode(', ', $placeholders) . ")";
                   
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute(array_values($data));
            
            if ($result) {
                return $pdo->lastInsertId();
            } else {
                return false;
            }
        }
    } catch (PDOException $e) {
        error_log("Insert failed for table {$table}: " . $e->getMessage());
        error_log("SQL: INSERT INTO " . $table . " (" . implode(', ', $columns) . ")");
        error_log("Data: " . json_encode($data));
        return null;
    }
}

/**
 * Helper function to update a record
 * 
 * @param string $table The table to update
 * @param array $data Associative array of column => value to update
 * @param string $where The WHERE clause
 * @param array $whereParams Parameters for the WHERE clause
 * @return bool True on success, false on failure
 */
function db_update($table, $data, $where, $whereParams = []) {
    try {
        $setClauses = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setClauses[] = "$column = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE " . $table . " SET " . implode(', ', $setClauses) . " WHERE " . $where;
        
        // Combine the SET parameters with the WHERE parameters
        $allParams = array_merge($params, $whereParams);
        
        $stmt = execute_query($sql, $allParams);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to delete records
 * 
 * @param string $table The table to delete from
 * @param string $where The WHERE clause
 * @param array $params Parameters for the WHERE clause
 * @return bool True on success, false on failure
 */
function db_delete($table, $where, $params = []) {
    try {
        $sql = "DELETE FROM " . $table . " WHERE " . $where;
        $stmt = execute_query($sql, $params);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Delete failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to execute a SQL query
 * This function is used for INSERT, UPDATE, DELETE operations
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind to the query
 * @return PDOStatement|bool The executed statement or false on failure
 */
function db_query($sql, $params = []) {
    try {
        return execute_query($sql, $params);
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));
        return false;
    }
}