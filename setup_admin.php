<?php
// This script helps set up the initial admin user for the Library Management System

// Database configuration
$host = 'localhost';
$dbname = 'library_management_system';
$username = 'root';
$password = '';

// Admin user details
$admin_name = 'Admin User';
$admin_email = 'admin@library.com';
$admin_password = 'admin123'; // This will be hashed before storing
$admin_phone = '1234567890';
$admin_address = 'Library Address';
$admin_type = 'admin';
$admin_status = 'active';
$admin_role = 'administrator';
$admin_hire_date = date('Y-m-d'); // Current date

// Create database connection
try {
    // First try to connect to MySQL server
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists, if not create it
    $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "<p>✓ Database '$dbname' is ready.</p>";
    
    // Connect to the library database
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if users table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "<p>× Users table does not exist. Please run the library_schema.sql script first.</p>";
        echo "<p>Go to <a href='http://localhost/phpmyadmin/' target='_blank'>phpMyAdmin</a>, select SQL tab, and import the library_schema.sql file.</p>";
        exit;
    }
    
    // Check if admin user already exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$admin_email]);
    if ($stmt->rowCount() > 0) {
        echo "<p>✓ Admin user already exists.</p>";
    } else {
        // Create admin user
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, address, user_type, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$admin_name, $admin_email, $hashed_password, $admin_phone, $admin_address, $admin_type, $admin_status]);
        $user_id = $conn->lastInsertId();
        
        echo "<p>✓ Admin user created successfully.</p>";
        
        // Create staff record for admin
        $stmt = $conn->prepare("INSERT INTO staff (user_id, role, hire_date) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $admin_role, $admin_hire_date]);
        
        echo "<p>✓ Admin staff record created successfully.</p>";
    }
    
    echo "<div style='margin-top: 20px; padding: 10px; background-color: #e8f5e9; border-radius: 5px;'>";
    echo "<h3>Setup Complete!</h3>";
    echo "<p>You can now log in with the following credentials:</p>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> $admin_email</li>";
    echo "<li><strong>Password:</strong> $admin_password</li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Go to Login Page</a></p>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>Connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure XAMPP is running with MySQL service started.</p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System - Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        p {
            margin: 10px 0;
        }
        a {
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Library Management System - Setup</h1>
        <p>This script helps you set up the initial admin user for the Library Management System.</p>
        <p>For complete setup instructions, please refer to the <a href="README.md">README.md</a> file.</p>
    </div>
</body>
</html>