<?php
// Database configuration
$host = 'localhost';
$dbname = 'library_management_system';
$username = 'root';
$password = '';

// Create database connection
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to sanitize user inputs
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function is_admin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
}

// Function to calculate fine for overdue books
// $1 per day overdue
function calculate_fine($due_date) {
    $due = new DateTime($due_date);
    $today = new DateTime();
    
    if ($today > $due) {
        $diff = $today->diff($due);
        return $diff->days;
    }
    
    return 0;
}

// Function to generate a unique ID
function generate_unique_id($prefix = '') {
    return uniqid($prefix) . bin2hex(random_bytes(4));
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>