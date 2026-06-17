<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once 'config.php';

// Initialize variables
$email = $password = '';
$error = '';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        try {
            // Check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Password is correct, store data in session variables
                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["user_type"] = $user["user_type"];
                
                // Check if user is staff
                if ($user["user_type"] == "staff" || $user["user_type"] == "admin") {
                    $stmt = $conn->prepare("SELECT * FROM staff WHERE user_id = ?");
                    $stmt->execute([$user["user_id"]]);
                    $staff = $stmt->fetch();
                    
                    if ($staff) {
                        $_SESSION["staff_id"] = $staff["staff_id"];
                        $_SESSION["role"] = $staff["role"];
                    }
                }
                
                // Force session write
                session_write_close();
                
                // Redirect to dashboard
                header("location: index.php");
                exit;
            } else {
                $error = "Invalid email or password";
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 15px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #3498db;
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
        }
        .logo {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .btn-login {
            background-color: #3498db;
            border-color: #3498db;
            width: 100%;
        }
        .btn-login:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <div class="logo">
                    <i class="fas fa-book-reader fa-2x"></i>
                </div>
                <h3>Library Management System</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-login">Login</button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>
    
    <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
    <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; max-width: 600px; margin-left: auto; margin-right: auto;">
        <h5>Debug Information:</h5>
        <ul style="font-family: monospace; font-size: 12px;">
            <li>Login attempt for: <?php echo htmlspecialchars($email); ?></li>
            <?php if (isset($user)): ?>
            <li>User found: Yes (ID: <?php echo htmlspecialchars($user['user_id']); ?>)</li>
            <li>Password verification: <?php echo password_verify($password, $user['password']) ? 'Success' : 'Failed'; ?></li>
            <?php else: ?>
            <li>User found: No</li>
            <?php endif; ?>
        </ul>
        
        <h5>Session Data:</h5>
        <ul style="font-family: monospace; font-size: 12px;">
            <?php if (empty($_SESSION)): ?>
            <li>No session data</li>
            <?php else: ?>
                <?php foreach($_SESSION as $key => $value): ?>
                <li><?php echo htmlspecialchars($key); ?>: <?php echo htmlspecialchars(print_r($value, true)); ?></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        
        <h5>PHP Info:</h5>
        <ul style="font-family: monospace; font-size: 12px;">
            <li>PHP Version: <?php echo phpversion(); ?></li>
            <li>Session Save Path: <?php echo session_save_path(); ?></li>
            <li>Session Status: <?php echo session_status() == PHP_SESSION_ACTIVE ? 'Active' : 'Not Active'; ?></li>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/script.js"></script>
</body>
</html>