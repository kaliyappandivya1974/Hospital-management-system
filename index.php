<?php
/**
 * AKIRA HOSPITAL Management System
 * Index Page - Redirects to login or dashboard based on session
 */

// Start session
session_start();

// Check if user is logged in
if (isset($_SESSION['admin_id'])) {
    // If logged in, redirect to dashboard
    header("Location: dashboard.php");
    exit;
} else {
    // If not logged in, redirect to login
    header("Location: login.php");
    exit;
}