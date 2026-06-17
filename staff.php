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
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = $error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new staff
    if (isset($_POST['add_staff'])) {
        $user_id = intval($_POST['user_id']);
        $role = sanitize_input($_POST['role']);
        $hire_date = sanitize_input($_POST['hire_date']);
        $salary = floatval($_POST['salary']);
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("User not found!");
            }
            
            // Check if user is already a staff member
            $stmt = $conn->prepare("SELECT * FROM staff WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $existing_staff = $stmt->fetch();
            
            if ($existing_staff) {
                throw new Exception("This user is already a staff member!");
            }
            
            // Insert new staff record
            $stmt = $conn->prepare("INSERT INTO staff (user_id, role, hire_date, salary) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $role, $hire_date, $salary]);
            
            // Update user type to staff or admin based on role
            $user_type = ($role == 'admin') ? 'admin' : 'staff';
            $stmt = $conn->prepare("UPDATE users SET user_type = ? WHERE user_id = ?");
            $stmt->execute([$user_type, $user_id]);
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Staff member added successfully!";
            $action = 'list'; // Return to list view
        } catch(Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Edit staff
    if (isset($_POST['edit_staff'])) {
        $staff_id = intval($_POST['staff_id']);
        $role = sanitize_input($_POST['role']);
        $hire_date = sanitize_input($_POST['hire_date']);
        $salary = floatval($_POST['salary']);
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Get staff record
            $stmt = $conn->prepare("SELECT * FROM staff WHERE staff_id = ?");
            $stmt->execute([$staff_id]);
            $staff = $stmt->fetch();
            
            if (!$staff) {
                throw new Exception("Staff record not found!");
            }
            
            // Update staff record
            $stmt = $conn->prepare("UPDATE staff SET 
                                   role = ?, 
                                   hire_date = ?, 
                                   salary = ? 
                                   WHERE staff_id = ?");
            $stmt->execute([$role, $hire_date, $salary, $staff_id]);
            
            // Update user type to staff or admin based on role
            $user_type = ($role == 'admin') ? 'admin' : 'staff';
            $stmt = $conn->prepare("UPDATE users SET user_type = ? WHERE user_id = ?");
            $stmt->execute([$user_type, $staff['user_id']]);
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Staff information updated successfully!";
            $action = 'list'; // Return to list view
        } catch(Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Delete staff
    if (isset($_POST['delete_staff'])) {
        $staff_id = intval($_POST['staff_id']);
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Get staff record
            $stmt = $conn->prepare("SELECT * FROM staff WHERE staff_id = ?");
            $stmt->execute([$staff_id]);
            $staff = $stmt->fetch();
            
            if (!$staff) {
                throw new Exception("Staff record not found!");
            }
            
            // Check if trying to delete own account
            if ($staff['user_id'] == $_SESSION['user_id']) {
                throw new Exception("You cannot delete your own staff account!");
            }
            
            // Delete staff record
            $stmt = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
            $stmt->execute([$staff_id]);
            
            // Update user type to student (default)
            $stmt = $conn->prepare("UPDATE users SET user_type = 'student' WHERE user_id = ?");
            $stmt->execute([$staff['user_id']]);
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Staff member removed successfully!";
            $action = 'list'; // Return to list view
        } catch(Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get staff details for view/edit
if (($action == 'view' || $action == 'edit') && $staff_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT s.*, u.name, u.email, u.phone, u.user_type 
                               FROM staff s 
                               JOIN users u ON s.user_id = u.user_id 
                               WHERE s.staff_id = ?");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();
        
        if (!$staff) {
            $error_message = "Staff record not found!";
            $action = 'list';
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get all staff for list view
if ($action == 'list') {
    try {
        $stmt = $conn->prepare("SELECT s.*, u.name, u.email, u.phone, u.user_type 
                               FROM staff s 
                               JOIN users u ON s.user_id = u.user_id 
                               ORDER BY s.staff_id");
        $stmt->execute();
        $staff_members = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get eligible users for add form
if ($action == 'add') {
    try {
        // Get users who are not already staff members
        $stmt = $conn->prepare("SELECT u.* 
                               FROM users u 
                               LEFT JOIN staff s ON u.user_id = s.user_id 
                               WHERE s.staff_id IS NULL AND u.status = 'active' 
                               ORDER BY u.name");
        $stmt->execute();
        $eligible_users = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
                            <a class="nav-link active" href="staff.php">
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
                            <a class="nav-link" href="settings.php">
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
                    <h1 class="h2">Staff Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($action == 'list'): ?>
                        <a href="?action=add" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add Staff Member
                        </a>
                        <?php else: ?>
                        <a href="?action=list" class="btn btn-sm btn-secondary">
                            <i class="fas fa-list"></i> Back to List
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if ($action == 'list'): ?>
                <!-- Staff List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-tie me-1"></i> Staff Members
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="staffTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Hire Date</th>
                                        <th>Salary</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($staff_members) && count($staff_members) > 0): ?>
                                        <?php foreach ($staff_members as $staff): ?>
                                        <tr>
                                            <td><?php echo $staff['staff_id']; ?></td>
                                            <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                            <td>
                                                <?php 
                                                $badge_class = '';
                                                switch($staff['role']) {
                                                    case 'admin':
                                                        $badge_class = 'bg-danger';
                                                        break;
                                                    case 'librarian':
                                                        $badge_class = 'bg-primary';
                                                        break;
                                                    case 'assistant':
                                                        $badge_class = 'bg-info text-dark';
                                                        break;
                                                    default:
                                                        $badge_class = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($staff['role'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($staff['hire_date'])); ?></td>
                                            <td>$<?php echo number_format($staff['salary'], 2); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?action=view&id=<?php echo $staff['staff_id']; ?>" class="btn btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="?action=edit&id=<?php echo $staff['staff_id']; ?>" class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($staff['user_id'] != $_SESSION['user_id']): ?>
                                                    <button type="button" class="btn btn-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $staff['staff_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $staff['staff_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to remove <strong><?php echo htmlspecialchars($staff['name']); ?></strong> from staff?
                                                                <p class="text-info">Note: This will only remove their staff status. The user account will remain active.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="post" action="">
                                                                    <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                                                    <button type="submit" name="delete_staff" class="btn btn-danger">Remove from Staff</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No staff members found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($action == 'add'): ?>
                <!-- Add Staff Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-plus me-1"></i> Add Staff Member
                    </div>
                    <div class="card-body">
                        <?php if (isset($eligible_users) && count($eligible_users) > 0): ?>
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="user_id" class="form-label">Select User</label>
                                <select class="form-select" id="user_id" name="user_id" required>
                                    <option value="">-- Select User --</option>
                                    <?php foreach ($eligible_users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select an existing user to add as staff member.</div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="librarian">Librarian</option>
                                    <option value="assistant">Assistant</option>
                                    <option value="admin">Administrator</option>
                                    <option value="intern">Intern</option>
                                </select>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="hire_date" class="form-label">Hire Date</label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="salary" class="form-label">Salary</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="salary" name="salary" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="add_staff" class="btn btn-primary">Add Staff Member</button>
                                <a href="?action=list" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> There are no eligible users to add as staff members. 
                            <a href="users.php?action=add" class="alert-link">Add a new user</a> first.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php elseif ($action == 'edit' && isset($staff)): ?>
                <!-- Edit Staff Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-edit me-1"></i> Edit Staff Member
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($staff['name']); ?>" readonly>
                                    <div class="form-text">To change user details, go to <a href="users.php?action=edit&id=<?php echo $staff['user_id']; ?>">User Management</a>.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($staff['email']); ?>" readonly>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="librarian" <?php echo $staff['role'] == 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                                    <option value="assistant" <?php echo $staff['role'] == 'assistant' ? 'selected' : ''; ?>>Assistant</option>
                                    <option value="admin" <?php echo $staff['role'] == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                    <option value="intern" <?php echo $staff['role'] == 'intern' ? 'selected' : ''; ?>>Intern</option>
                                </select>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="hire_date" class="form-label">Hire Date</label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" value="<?php echo date('Y-m-d', strtotime($staff['hire_date'])); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="salary" class="form-label">Salary</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="salary" name="salary" step="0.01" min="0" value="<?php echo $staff['salary']; ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="edit_staff" class="btn btn-primary">Update Staff Member</button>
                                <a href="?action=list" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php elseif ($action == 'view' && isset($staff)): ?>
                <!-- View Staff Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-tie me-1"></i> Staff Member Details
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Personal Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Staff ID</th>
                                        <td><?php echo $staff['staff_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Name</th>
                                        <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone</th>
                                        <td><?php echo htmlspecialchars($staff['phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>User Type</th>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo ucfirst(htmlspecialchars($staff['user_type'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Staff Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Role</th>
                                        <td>
                                            <?php 
                                            $badge_class = '';
                                            switch($staff['role']) {
                                                case 'admin':
                                                    $badge_class = 'bg-danger';
                                                    break;
                                                case 'librarian':
                                                    $badge_class = 'bg-primary';
                                                    break;
                                                case 'assistant':
                                                    $badge_class = 'bg-info text-dark';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo ucfirst(htmlspecialchars($staff['role'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Hire Date</th>
                                        <td><?php echo date('Y-m-d', strtotime($staff['hire_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Salary</th>
                                        <td>$<?php echo number_format($staff['salary'], 2); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="?action=edit&id=<?php echo $staff['staff_id']; ?>" class="btn btn-primary">Edit Staff Member</a>
                            <a href="users.php?action=view&id=<?php echo $staff['user_id']; ?>" class="btn btn-info">View User Profile</a>
                            <a href="?action=list" class="btn btn-secondary">Back to List</a>
                        </div>
                    </div>
                </div>
                <?php