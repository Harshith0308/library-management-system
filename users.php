<?php
// Include database configuration
require_once 'config.php';

// Helper function to get status badge class
function get_status_class($status) {
    switch($status) {
        case 'active':
            return 'bg-success';
        case 'inactive':
            return 'bg-warning text-dark';
        case 'suspended':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Process form submissions
if (isset($_POST['add_user'])) {
    // Add user logic
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $user_type = $_POST['user_type'];
    $department = $_POST['department'];
    $status = $_POST['status'];
    
    try {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, address, user_type, department, status, registration_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $email, $password, $phone, $address, $user_type, $department, $status]);
        $_SESSION['success_msg'] = "User added successfully!";
        header('Location: users.php?action=list');
        exit;
    } catch(PDOException $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
} elseif (isset($_POST['update_user'])) {
    // Update user logic
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $user_type = $_POST['user_type'];
    $department = $_POST['department'];
    $status = $_POST['status'];
    
    try {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, 
                              user_type = ?, department = ?, status = ? WHERE user_id = ?");
        $stmt->execute([$name, $email, $phone, $address, $user_type, $department, $status, $user_id]);
        $_SESSION['success_msg'] = "User updated successfully!";
        header('Location: users.php?action=list');
        exit;
    } catch(PDOException $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
} elseif (isset($_POST['change_password'])) {
    // Change password logic
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords match
    if ($new_password !== $confirm_password) {
        $error_msg = "Passwords do not match!";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $_SESSION['success_msg'] = "Password changed successfully!";
            header('Location: users.php?action=list');
            exit;
        } catch(PDOException $e) {
            $error_msg = "Error: " . $e->getMessage();
        }
    }
}

// Handle delete action
if ($action == 'delete' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    try {
        // Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error_msg'] = "User not found!";
            header('Location: users.php?action=list');
            exit;
        }
        
        // Check if user has any active loans
        $stmt = $conn->prepare("SELECT COUNT(*) FROM lending WHERE user_id = ? AND return_date IS NULL");
        $stmt->execute([$user_id]);
        $active_loans = $stmt->fetchColumn();
        
        if ($active_loans > 0) {
            $_SESSION['error_msg'] = "Cannot delete user with active loans. Please return all books first."; 
            header('Location: users.php?action=list');
            exit;
        }
        
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success_msg'] = "User deleted successfully!";
        header('Location: users.php?action=list');
        exit;
    } catch(PDOException $e) {
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
        header('Location: users.php?action=list');
        exit;
    }
}

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Handle user data retrieval for edit/view
if (in_array($action, ['edit', 'view', 'change_password']) && isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error_msg'] = "User not found!";
            header('Location: users.php?action=list');
            exit;
        }
    } catch(PDOException $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Page title based on action
$page_title = "User Management";
switch($action) {
    case 'add':
        $page_title = "Add New User";
        break;
    case 'edit':
        $page_title = "Edit User";
        break;
    case 'view':
        $page_title = "User Details";
        break;
    case 'change_password':
        $page_title = "Change Password";
        break;
}

// Include header
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4"><?php echo $page_title; ?></h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
            </ol>
            
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger"><?php echo $error_msg; ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success_msg']; ?></div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error_msg']; ?></div>
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>
            
            <?php if ($action == 'list'): ?>
                <!-- User List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><i class="fas fa-users me-1"></i> User List</div>
                            <a href="?action=add" class="btn btn-primary btn-sm">Add New User</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="datatablesSimple" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>User Type</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT * FROM users ORDER BY user_id DESC");
                                    while ($row = $stmt->fetch()) {
                                        echo "<tr>";
                                        echo "<td>{$row['user_id']}</td>";
                                        echo "<td>{$row['name']}</td>";
                                        echo "<td>{$row['email']}</td>";
                                        echo "<td>" . ucfirst($row['user_type']) . "</td>";
                                        echo "<td>{$row['department']}</td>";
                                        
                                        // Status badge
                                        $status_class = '';
                                        switch($row['status']) {
                                            case 'active':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'inactive':
                                                $status_class = 'bg-warning text-dark';
                                                break;
                                            case 'suspended':
                                                $status_class = 'bg-danger';
                                                break;
                                        }
                                        echo "<td><span class='badge {$status_class}'>" . ucfirst($row['status']) . "</span></td>";
                                        
                                        // Action buttons
                                        echo "<td>";
                                        echo "<a href='?action=view&id={$row['user_id']}' class='btn btn-info btn-sm me-1'><i class='fas fa-eye'></i></a>";
                                        echo "<a href='?action=edit&id={$row['user_id']}' class='btn btn-primary btn-sm me-1'><i class='fas fa-edit'></i></a>";
                                        echo "<a href='?action=change_password&id={$row['user_id']}' class='btn btn-warning btn-sm me-1'><i class='fas fa-key'></i></a>";
                                        echo "<a href='#' class='btn btn-danger btn-sm' data-bs-toggle='modal' data-bs-target='#deleteModal{$row['user_id']}'><i class='fas fa-trash'></i></a>";
                                        echo "</td>";
                                        echo "</tr>";
                                        
                                        // Delete confirmation modal
                                        echo "<div class='modal fade' id='deleteModal{$row['user_id']}' tabindex='-1' aria-labelledby='deleteModalLabel' aria-hidden='true'>";
                                        echo "<div class='modal-dialog'>";
                                        echo "<div class='modal-content'>";
                                        echo "<div class='modal-header'>";
                                        echo "<h5 class='modal-title' id='deleteModalLabel'>Confirm Delete</h5>";
                                        echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>";
                                        echo "</div>";
                                        echo "<div class='modal-body'>";
                                        echo "Are you sure you want to delete user: <strong>{$row['name']}</strong>?";
                                        echo "</div>";
                                        echo "<div class='modal-footer'>";
                                        echo "<button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>";
                                        echo "<a href='?action=delete&id={$row['user_id']}' class='btn btn-danger'>Delete</a>";
                                        echo "</div>";
                                        echo "</div>";
                                        echo "</div>";
                                        echo "</div>";
                                    }
                                } catch(PDOException $e) {
                                    echo "<tr><td colspan='7' class='text-danger'>Error: " . $e->getMessage() . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php elseif ($action == 'add'): ?>
                <!-- Add User Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-plus me-1"></i> Add New User
                    </div>
                    <div class="card-body">
                        <form action="users.php" method="post">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="user_type" class="form-label">User Type</label>
                                        <select class="form-select" id="user_type" name="user_type" required>
                                            <option value="">Select User Type</option>
                                            <option value="admin">Admin</option>
                                            <option value="librarian">Librarian</option>
                                            <option value="student">Student</option>
                                            <option value="faculty">Faculty</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <input type="text" class="form-control" id="department" name="department">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="suspended">Suspended</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="users.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php elseif ($action == 'edit'): ?>
                <!-- Edit User Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-edit me-1"></i> Edit User
                    </div>
                    <div class="card-body">
                        <form action="users.php" method="post">
                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="user_type" class="form-label">User Type</label>
                                        <select class="form-select" id="user_type" name="user_type" required>
                                            <option value="admin" <?php echo ($user['user_type'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            <option value="librarian" <?php echo ($user['user_type'] == 'librarian') ? 'selected' : ''; ?>>Librarian</option>
                                            <option value="student" <?php echo ($user['user_type'] == 'student') ? 'selected' : ''; ?>>Student</option>
                                            <option value="faculty" <?php echo ($user['user_type'] == 'faculty') ? 'selected' : ''; ?>>Faculty</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($user['department']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?php echo ($user['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($user['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="suspended" <?php echo ($user['status'] == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="users.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php elseif ($action == 'view'): ?>
                <!-- View User Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user me-1"></i> User Details
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($user['address']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>User Type:</strong> <?php echo ucfirst(htmlspecialchars($user['user_type'])); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($user['department']); ?></p>
                                <p><strong>Status:</strong> <span class="badge <?php echo get_status_class($user['status']); ?>"><?php echo ucfirst($user['status']); ?></span></p>
                                <p><strong>Registration Date:</strong> <?php echo date('F j, Y', strtotime($user['registration_date'])); ?></p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="users.php" class="btn btn-secondary">Back to List</a>
                            <a href="?action=edit&id=<?php echo $user['user_id']; ?>" class="btn btn-primary">Edit User</a>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($action == 'change_password'): ?>
                <!-- Change Password Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-key me-1"></i> Change Password for <?php echo htmlspecialchars($user['name']); ?>
                    </div>
                    <div class="card-body">
                        <form action="users.php" method="post">
                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="users.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<!-- Custom JS -->
<script src="js/script.js"></script>
</body>
</html>