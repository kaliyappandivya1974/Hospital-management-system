<?php
/**
 * AKIRA HOSPITAL Management System
 * Enhanced Login Page for XAMPP PostgreSQL
 */

// Start session
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Define variables and set to empty values
$username = $password = "";
$error = "";

// Process login form when submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get username and password
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        try {
            // Admin direct login for XAMPP compatibility
            if ($username === 'admin' && $password === 'admin123') {
                error_log("Using admin direct login for XAMPP compatibility");

                // Login successful - set session
                $_SESSION['admin_id'] = 1;
                $_SESSION['admin_username'] = 'admin';
                $_SESSION['admin_name'] = 'System Admin';
                $_SESSION['admin_role'] = 'admin';
                $_SESSION['login_success'] = 'Welcome to AKIRA HOSPITAL Management System';

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            }

            // Attempt to find user in database
            $sql = "SELECT * FROM users WHERE username = ?";
            $user = db_get_row($sql, [$username]);

            // If user not found in users table, try admins table (for XAMPP compatibility)
            if (!$user) {
                $sql = "SELECT * FROM admins WHERE username = ?";
                $user = db_get_row($sql, [$username]);
            }

            // Regular login verification
            if ($user) {
                // Verify password with hash if available
                $passwordVerified = false;

                if (isset($user['password']) && !empty($user['password'])) {
                    // Check if it's a hashed password
                    if (substr($user['password'], 0, 1) === '$') {
                        // It's a hash, verify with password_verify
                        $passwordVerified = password_verify($password, $user['password']);
                    } else {
                        // Direct comparison (not recommended for production)
                        $passwordVerified = ($password === $user['password']);
                    }
                }

                if ($passwordVerified) {
                    // Login successful - set session
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_name'] = $user['name'] ?? 'User';
                    $_SESSION['admin_role'] = $user['role'] ?? 'staff';
                    $_SESSION['login_success'] = 'Welcome to AKIRA HOSPITAL Management System';

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Invalid username or password";
                }
            } else {
                $error = "Invalid username or password";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "Database error occurred. Please check the server logs or contact administrator.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKIRA HOSPITAL - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/lucide/1.0.0/lucide.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(to bottom, #d4f1f9 60%, #8cd9a5 100%);
        }
        .gradient-button {
            background: linear-gradient(to right, #3b82f6, #2563eb);
        }
        .gradient-button:hover {
            background: linear-gradient(to right, #2563eb, #1d4ed8);
        }
        .blur-bg {
            backdrop-filter: blur(10px);
        }
        .login-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col gradient-bg">
    <div class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-[1000px] grid grid-cols-1 md:grid-cols-2 shadow-lg rounded-xl overflow-hidden">
            <!-- Left panel - Login Form -->
            <div class="bg-gradient-to-b from-gray-900 to-blue-900 text-white p-8 flex flex-col">
                <div class="mb-8 text-center">
                    <div class="flex justify-center mb-4">
                        <div class="p-4 bg-teal-500 rounded-full inline-flex">
                            <i data-lucide="building-2" class="h-12 w-12"></i>
                        </div>
                    </div>
                    <h1 class="text-4xl font-bold tracking-tight text-teal-400">AKIRA</h1>
                    <h1 class="text-4xl font-bold tracking-tight text-teal-400 mb-1">HOSPITAL</h1>
                    <p class="text-gray-300">Hospital Management System</p>
                </div>

                <div class="mb-6 mt-4">
                    <h2 class="text-xl font-semibold text-center mb-4">Admin Login</h2>

                    <?php if (!empty($error)): ?>
                        <div class="bg-red-500/20 text-red-200 p-3 rounded mb-4 text-sm">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-4">
                        <div>
                            <label class="block text-gray-300 mb-2">Username</label>
                            <div class="relative">
                                <i data-lucide="user" class="absolute left-3 top-2.5 h-4 w-4 text-gray-400"></i>
                                <input 
                                    name="username"
                                    class="w-full pl-10 bg-gray-800 border-gray-700 text-white rounded-md p-2 border focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Enter your username"
                                    value="<?php echo htmlspecialchars($username); ?>"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-300 mb-2">Password</label>
                            <div class="relative">
                                <i data-lucide="lock" class="absolute left-3 top-2.5 h-4 w-4 text-gray-400"></i>
                                <input 
                                    name="password"
                                    type="password"
                                    class="w-full pl-10 bg-gray-800 border-gray-700 text-white rounded-md p-2 border focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Enter your password"
                                />
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full gradient-button text-white font-semibold py-2 rounded-md flex items-center justify-center"
                            id="loginButton"
                        >
                            <span id="loginText">
                                <i data-lucide="log-in" class="h-4 w-4 mr-2"></i>
                                Login to Dashboard
                            </span>
                            <span id="loginLoading" class="hidden">
                                <span class="login-spinner mr-2"></span>
                                Logging in...
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right panel - Hospital Info -->
            <div class="bg-blue-600 text-white p-8 flex flex-col relative">
                <div class="mb-4">
                    <span class="bg-white/20 text-white hover:bg-white/30 blur-bg inline-flex items-center rounded-full px-3 py-1 text-sm font-medium">
                        Excellence in Healthcare
                    </span>
                </div>

                <div class="mt-8">
                    <h2 class="text-2xl font-bold mb-2">Welcome to <span class="text-yellow-300">AKIRA HOSPITAL</span></h2>
                    <p class="text-lg italic mb-6">Healing Hands, Caring Hearts</p>

                    <p class="mb-6">
                        Providing exceptional healthcare services with cutting-edge technology and compassionate care
                        since 1995. Our state-of-the-art facilities are designed to meet all your medical needs.
                    </p>

                    <p class="mb-6">
                        Use this secure portal to access the hospital management system. This system helps
                        manage patient records, appointments, billing, and more.
                    </p>

                    <div class="mt-auto space-y-4">
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-blue-500/50 p-3 rounded-lg blur-bg">
                                <div class="text-sm font-medium mb-1 text-center">Expert Doctors</div>
                            </div>
                            <div class="bg-blue-500/50 p-3 rounded-lg blur-bg">
                                <div class="text-sm font-medium mb-1 text-center">Modern Facilities</div>
                            </div>
                            <div class="bg-blue-500/50 p-3 rounded-lg blur-bg">
                                <div class="text-sm font-medium mb-1 text-center">Emergency Care</div>
                            </div>
                        </div>

                        <div>
                            <h3 class="font-medium mb-1">Contact Us</h3>
                            <p class="text-sm">Phone: +91 98765 43210</p>
                            <p class="text-sm">Email: info@akirahospital.com</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="bg-gray-900 text-white py-4 px-6">
        <div class="container mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-center justify-center md:justify-start">
                    <h3 class="text-lg font-medium">Hospital Hours</h3>
                </div>

                <div class="grid grid-cols-2 gap-2 text-center md:text-left text-sm">
                    <div>Monday - Friday:</div>
                    <div>8:00 AM - 8:00 PM</div>
                    <div>Saturday:</div>
                    <div>9:00 AM - 6:00 PM</div>
                    <div>Sunday:</div>
                    <div>10:00 AM - 4:00 PM</div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-center md:text-right text-sm">
                    <div>Emergency:</div>
                    <div class="text-red-400">24/7</div>
                    <div>Lab Services:</div>
                    <div>7:00 AM - 7:00 PM</div>
                    <div>Pharmacy:</div>
                    <div>8:00 AM - 10:00 PM</div>
                </div>
            </div>

            <div class="mt-4 text-center text-sm text-gray-400">
                © <?php echo date("Y"); ?> AKIRA HOSPITAL. All rights reserved.
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/1.0.0/lucide.min.js"></script>
    <script>
        lucide.createIcons();

        // Show loading spinner on form submission
        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('loginText').classList.add('hidden');
            document.getElementById('loginLoading').classList.remove('hidden');
        });

        // Focus on username field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput) {
                usernameInput.focus();
            }
        });
    </script>
</body>
</html>