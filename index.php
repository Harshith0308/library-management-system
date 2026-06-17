<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_type = $_SESSION['user_type'];

// Get dashboard statistics
try {
    // Total books
    $stmt = $conn->query("SELECT COUNT(*) as total FROM books");
    $total_books = $stmt->fetch()['total'];
    
    // Available books
    $stmt = $conn->query("SELECT SUM(available_copies) as available FROM books");
    $available_books = $stmt->fetch()['available'];
    
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    
    // Books currently borrowed
    $stmt = $conn->query("SELECT COUNT(*) as total FROM book_lending WHERE return_date IS NULL");
    $borrowed_books = $stmt->fetch()['total'];
    
    // Overdue books
    $stmt = $conn->query("SELECT COUNT(*) as total FROM book_lending WHERE due_date < CURDATE() AND return_date IS NULL");
    $overdue_books = $stmt->fetch()['total'];
    
    // Recent activities
    $stmt = $conn->query("SELECT bl.lending_id, b.title, u.name, bl.issue_date, bl.due_date, bl.status 
                         FROM book_lending bl 
                         JOIN book_copies bc ON bl.copy_id = bc.copy_id 
                         JOIN books b ON bc.book_id = b.book_id 
                         JOIN users u ON bl.user_id = u.user_id 
                         ORDER BY bl.issue_date DESC LIMIT 5");
    $recent_activities = $stmt->fetchAll();
    
    // Pending book requests
    $stmt = $conn->query("SELECT COUNT(*) as total FROM book_requests WHERE status = 'pending'");
    $pending_requests = $stmt->fetch()['total'];
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Library Management System</title>
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
                            <a class="nav-link active" href="index.php">
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
                        <?php if ($user_type == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="staff.php">
                                <i class="fas fa-user-tie"></i> Staff
                            </a>
                        </li>
                        <?php endif; ?>
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Print</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar"></i> This week
                        </button>
                    </div>
                </div>

                <!-- Welcome message -->
                <div class="alert alert-info" role="alert">
                    <h4 class="alert-heading">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h4>
                    <p>You are logged in as <?php echo htmlspecialchars($user_type); ?>.</p>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 col-xl-3 mb-4">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="me-3">
                                        <div class="text-white-75 small">Total Books</div>
                                        <div class="text-lg fw-bold"><?php echo $total_books; ?></div>
                                    </div>
                                    <i class="fas fa-book fa-2x text-white-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between small">
                                <a class="text-white stretched-link" href="books.php">View Details</a>
                                <div class="text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-3 mb-4">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="me-3">
                                        <div class="text-white-75 small">Available Books</div>
                                        <div class="text-lg fw-bold"><?php echo $available_books; ?></div>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x text-white-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between small">
                                <a class="text-white stretched-link" href="books.php?filter=available">View Details</a>
                                <div class="text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-3 mb-4">
                        <div class="card bg-warning text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="me-3">
                                        <div class="text-white-75 small">Borrowed Books</div>
                                        <div class="text-lg fw-bold"><?php echo $borrowed_books; ?></div>
                                    </div>
                                    <i class="fas fa-exchange-alt fa-2x text-white-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between small">
                                <a class="text-white stretched-link" href="circulation.php?filter=borrowed">View Details</a>
                                <div class="text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-3 mb-4">
                        <div class="card bg-danger text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="me-3">
                                        <div class="text-white-75 small">Overdue Books</div>
                                        <div class="text-lg fw-bold"><?php echo $overdue_books; ?></div>
                                    </div>
                                    <i class="fas fa-clock fa-2x text-white-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between small">
                                <a class="text-white stretched-link" href="circulation.php?filter=overdue">View Details</a>
                                <div class="text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Activities -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-history me-1"></i>
                                Recent Activities
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Book</th>
                                                <th>User</th>
                                                <th>Issue Date</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_activities as $activity): ?>
                                            <tr>
                                                <td><?php echo $activity['lending_id']; ?></td>
                                                <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                                <td><?php echo htmlspecialchars($activity['name']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($activity['issue_date'])); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($activity['due_date'])); ?></td>
                                                <td>
                                                    <?php if ($activity['status'] == 'borrowed'): ?>
                                                        <span class="badge bg-warning">Borrowed</span>
                                                    <?php elseif ($activity['status'] == 'returned'): ?>
                                                        <span class="badge bg-success">Returned</span>
                                                    <?php elseif ($activity['status'] == 'overdue'): ?>
                                                        <span class="badge bg-danger">Overdue</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer small text-muted">
                                <a href="circulation.php" class="btn btn-sm btn-primary">View All Activities</a>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions and Notifications -->
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-bolt me-1"></i>
                                Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <a href="books.php?action=add" class="list-group-item list-group-item-action">
                                        <i class="fas fa-plus me-2"></i> Add New Book
                                    </a>
                                    <a href="circulation.php?action=issue" class="list-group-item list-group-item-action">
                                        <i class="fas fa-paper-plane me-2"></i> Issue Book
                                    </a>
                                    <a href="circulation.php?action=return" class="list-group-item list-group-item-action">
                                        <i class="fas fa-undo me-2"></i> Return Book
                                    </a>
                                    <a href="users.php?action=add" class="list-group-item list-group-item-action">
                                        <i class="fas fa-user-plus me-2"></i> Add New User
                                    </a>
                                    <a href="reports.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-line me-2"></i> Generate Reports
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Notifications -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-bell me-1"></i>
                                Notifications
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php if ($overdue_books > 0): ?>
                                    <a href="circulation.php?filter=overdue" class="list-group-item list-group-item-action text-danger">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Overdue Books</h6>
                                            <small><?php echo $overdue_books; ?></small>
                                        </div>
                                        <p class="mb-1">There are <?php echo $overdue_books; ?> overdue books that need attention.</p>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($pending_requests > 0): ?>
                                    <a href="book_requests.php?filter=pending" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Pending Book Requests</h6>
                                            <small><?php echo $pending_requests; ?></small>
                                        </div>
                                        <p class="mb-1">There are <?php echo $pending_requests; ?> pending book requests.</p>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="#" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">System Update</h6>
                                            <small>3 days ago</small>
                                        </div>
                                        <p class="mb-1">The system has been updated to the latest version.</p>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
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