<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
} elseif (!is_admin()) {
    header("Location: index.php");
    exit;
}

// Initialize variables
$success_message = $error_message = '';

// Define default settings if not already in database
$default_settings = [
    'loan_period' => 14, // Default loan period in days
    'max_books_per_user' => 5, // Maximum books a user can borrow at once
    'fine_rate' => 1.00, // Fine rate per day for overdue books
    'max_renewals' => 2, // Maximum number of times a book can be renewed
    'renewal_period' => 7, // Renewal period in days
    'reservation_period' => 3, // How many days a reservation is held
    'library_name' => 'City Public Library', // Library name
    'library_address' => '123 Main Street, City, State 12345', // Library address
    'library_email' => 'library@example.com', // Library contact email
    'library_phone' => '(123) 456-7890', // Library contact phone
    'library_hours' => 'Monday-Friday: 9am-8pm, Saturday: 10am-6pm, Sunday: Closed', // Library hours
];

// Check if settings table exists, if not create it
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($stmt->rowCount() == 0) {
        // Create settings table
        $conn->exec("CREATE TABLE settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert default settings
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        foreach ($default_settings as $key => $value) {
            $description = ucwords(str_replace('_', ' ', $key));
            $stmt->execute([$key, $value, $description]);
        }
    }
} catch(PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_settings'])) {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Update each setting
            $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            
            foreach ($_POST as $key => $value) {
                // Skip non-setting fields
                if ($key == 'update_settings') continue;
                
                // Sanitize input
                $value = sanitize_input($value);
                
                // Update setting
                $stmt->execute([$value, $key]);
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Settings updated successfully!";
        } catch(Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get current settings
try {
    $stmt = $conn->query("SELECT * FROM settings ORDER BY setting_key");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row;
    }
} catch(PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="fas fa-book-reader fa-3x text-white"></i>
                        <h5 class="text-white mt-2">Library System</h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="books.php">
                                <i class="fas fa-book"></i> Books
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="authors.php">
                                <i class="fas fa-user-edit"></i> Authors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="publishers.php">
                                <i class="fas fa-building"></i> Publishers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="periodicals.php">
                                <i class="fas fa-newspaper"></i> Periodicals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="circulation.php">
                                <i class="fas fa-exchange-alt"></i> Circulation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="staff.php">
                                <i class="fas fa-user-tie"></i> Staff
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="book_requests.php">
                                <i class="fas fa-clipboard-list"></i> Book Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item mt-5">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">System Settings</h1>
                </div>

                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Settings Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-cog me-1"></i> Library Settings
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5>Library Information</h5>
                                    <hr>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="library_name" class="form-label">Library Name</label>
                                    <input type="text" class="form-control" id="library_name" name="library_name" value="<?php echo htmlspecialchars($settings['library_name']['setting_value'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="library_email" class="form-label">Library Email</label>
                                    <input type="email" class="form-control" id="library_email" name="library_email" value="<?php echo htmlspecialchars($settings['library_email']['setting_value'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="library_phone" class="form-label">Library Phone</label>
                                    <input type="text" class="form-control" id="library_phone" name="library_phone" value="<?php echo htmlspecialchars($settings['library_phone']['setting_value'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="library_hours" class="form-label">Library Hours</label>
                                    <input type="text" class="form-control" id="library_hours" name="library_hours" value="<?php echo htmlspecialchars($settings['library_hours']['setting_value'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="library_address" class="form-label">Library Address</label>
                                    <textarea class="form-control" id="library_address" name="library_address" rows="2" required><?php echo htmlspecialchars($settings['library_address']['setting_value'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5>Circulation Settings</h5>
                                    <hr>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="loan_period" class="form-label">Loan Period (days)</label>
                                    <input type="number" class="form-control" id="loan_period" name="loan_period" value="<?php echo htmlspecialchars($settings['loan_period']['setting_value'] ?? ''); ?>" min="1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="max_books_per_user" class="form-label">Max Books Per User</label>
                                    <input type="number" class="form-control" id="max_books_per_user" name="max_books_per_user" value="<?php echo htmlspecialchars($settings['max_books_per_user']['setting_value'] ?? ''); ?>" min="1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="fine_rate" class="form-label">Fine Rate ($ per day)</label>
                                    <input type="number" class="form-control" id="fine_rate" name="fine_rate" value="<?php echo htmlspecialchars($settings['fine_rate']['setting_value'] ?? ''); ?>" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="max_renewals" class="form-label">Max Renewals</label>
                                    <input type="number" class="form-control" id="max_renewals" name="max_renewals" value="<?php echo htmlspecialchars($settings['max_renewals']['setting_value'] ?? ''); ?>" min="0" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="renewal_period" class="form-label">Renewal Period (days)</label>
                                    <input type="number" class="form-control" id="renewal_period" name="renewal_period" value="<?php echo htmlspecialchars($settings['renewal_period']['setting_value'] ?? ''); ?>" min="1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="reservation_period" class="form-label">Reservation Hold Period (days)</label>
                                    <input type="number" class="form-control" id="reservation_period" name="reservation_period" value="<?php echo htmlspecialchars($settings['reservation_period']['setting_value'] ?? ''); ?>" min="1" required>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="js/script.js"></script>
</body>
</html>