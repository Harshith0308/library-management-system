<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Initialize variables
$report_type = isset($_GET['type']) ? $_GET['type'] : 'circulation';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$error_message = '';

// Generate report data based on type
try {
    switch ($report_type) {
        case 'circulation':
            // Books borrowed and returned in date range
            $stmt = $conn->prepare("SELECT 
                                   COUNT(CASE WHEN issue_date BETWEEN ? AND ? THEN 1 END) as books_borrowed,
                                   COUNT(CASE WHEN return_date BETWEEN ? AND ? THEN 1 END) as books_returned,
                                   SUM(CASE WHEN due_date < CURDATE() AND return_date IS NULL THEN 1 ELSE 0 END) as overdue_books,
                                   SUM(CASE WHEN fine_amount > 0 THEN fine_amount ELSE 0 END) as total_fines,
                                   SUM(CASE WHEN fine_amount > 0 AND fine_paid = 1 THEN fine_amount ELSE 0 END) as collected_fines
                                   FROM book_lending
                                   WHERE issue_date <= ? AND (return_date >= ? OR return_date IS NULL)");
            $stmt->execute([$start_date, $end_date, $start_date, $end_date, $end_date, $start_date]);
            $circulation_summary = $stmt->fetch();
            
            // Daily circulation statistics
            $stmt = $conn->prepare("SELECT 
                                   DATE(issue_date) as date,
                                   COUNT(*) as borrowed_count
                                   FROM book_lending
                                   WHERE issue_date BETWEEN ? AND ?
                                   GROUP BY DATE(issue_date)
                                   ORDER BY date");
            $stmt->execute([$start_date, $end_date]);
            $daily_borrows = $stmt->fetchAll();
            
            $stmt = $conn->prepare("SELECT 
                                   DATE(return_date) as date,
                                   COUNT(*) as returned_count
                                   FROM book_lending
                                   WHERE return_date BETWEEN ? AND ?
                                   GROUP BY DATE(return_date)
                                   ORDER BY date");
            $stmt->execute([$start_date, $end_date]);
            $daily_returns = $stmt->fetchAll();
            
            // Most borrowed books
            $stmt = $conn->prepare("SELECT 
                                   b.book_id, b.title, COUNT(*) as borrow_count
                                   FROM book_lending bl
                                   JOIN book_copies bc ON bl.copy_id = bc.copy_id
                                   JOIN books b ON bc.book_id = b.book_id
                                   WHERE bl.issue_date BETWEEN ? AND ?
                                   GROUP BY b.book_id
                                   ORDER BY borrow_count DESC
                                   LIMIT 10");
            $stmt->execute([$start_date, $end_date]);
            $popular_books = $stmt->fetchAll();
            break;
            
        case 'users':
            // User activity summary
            $stmt = $conn->prepare("SELECT 
                                   COUNT(DISTINCT user_id) as active_users,
                                   COUNT(*) as total_transactions,
                                   ROUND(COUNT(*) / COUNT(DISTINCT user_id), 2) as avg_transactions_per_user
                                   FROM book_lending
                                   WHERE issue_date BETWEEN ? AND ?");
            $stmt->execute([$start_date, $end_date]);
            $user_summary = $stmt->fetch();
            
            // Most active users
            $stmt = $conn->prepare("SELECT 
                                   u.user_id, u.name, u.email, u.user_type,
                                   COUNT(*) as borrow_count
                                   FROM book_lending bl
                                   JOIN users u ON bl.user_id = u.user_id
                                   WHERE bl.issue_date BETWEEN ? AND ?
                                   GROUP BY u.user_id
                                   ORDER BY borrow_count DESC
                                   LIMIT 10");
            $stmt->execute([$start_date, $end_date]);
            $active_users = $stmt->fetchAll();
            
            // Users with overdue books
            $stmt = $conn->prepare("SELECT 
                                   u.user_id, u.name, u.email, u.user_type,
                                   COUNT(*) as overdue_count,
                                   SUM(bl.fine_amount) as total_fines
                                   FROM book_lending bl
                                   JOIN users u ON bl.user_id = u.user_id
                                   WHERE bl.due_date < CURDATE() AND bl.return_date IS NULL
                                   GROUP BY u.user_id
                                   ORDER BY overdue_count DESC");
            $stmt->execute();
            $overdue_users = $stmt->fetchAll();
            
            // User registration trend
            $stmt = $conn->prepare("SELECT 
                                   DATE(registration_date) as date,
                                   COUNT(*) as new_users
                                   FROM users
                                   WHERE registration_date BETWEEN ? AND ?
                                   GROUP BY DATE(registration_date)
                                   ORDER BY date");
            $stmt->execute([$start_date, $end_date]);
            $user_registrations = $stmt->fetchAll();
            break;
            
        case 'fines':
            // Fine summary
            $stmt = $conn->prepare("SELECT 
                                   SUM(fine_amount) as total_fines,
                                   SUM(CASE WHEN fine_paid = 1 THEN fine_amount ELSE 0 END) as collected_fines,
                                   SUM(CASE WHEN fine_paid = 0 THEN fine_amount ELSE 0 END) as pending_fines,
                                   COUNT(CASE WHEN fine_amount > 0 THEN 1 END) as fined_transactions,
                                   COUNT(CASE WHEN fine_amount > 0 AND fine_paid = 1 THEN 1 END) as paid_transactions
                                   FROM book_lending
                                   WHERE (issue_date BETWEEN ? AND ? OR return_date BETWEEN ? AND ?)
                                   AND fine_amount > 0");
            $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
            $fine_summary = $stmt->fetch();
            
            // Daily fine collection
            $stmt = $conn->prepare("SELECT 
                                   DATE(return_date) as date,
                                   SUM(fine_amount) as fine_amount,
                                   COUNT(*) as transaction_count
                                   FROM book_lending
                                   WHERE return_date BETWEEN ? AND ? AND fine_amount > 0 AND fine_paid = 1
                                   GROUP BY DATE(return_date)
                                   ORDER BY date");
            $stmt->execute([$start_date, $end_date]);
            $daily_fines = $stmt->fetchAll();
            
            // Top users with fines
            $stmt = $conn->prepare("SELECT 
                                   u.user_id, u.name, u.email, u.user_type,
                                   SUM(bl.fine_amount) as total_fines,
                                   SUM(CASE WHEN bl.fine_paid = 1 THEN bl.fine_amount ELSE 0 END) as paid_fines,
                                   SUM(CASE WHEN bl.fine_paid = 0 THEN bl.fine_amount ELSE 0 END) as pending_fines
                                   FROM book_lending bl
                                   JOIN users u ON bl.user_id = u.user_id
                                   WHERE (bl.issue_date BETWEEN ? AND ? OR bl.return_date BETWEEN ? AND ?)
                                   AND bl.fine_amount > 0
                                   GROUP BY u.user_id
                                   ORDER BY total_fines DESC
                                   LIMIT 10");
            $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
            $users_with_fines = $stmt->fetchAll();
            break;
            
        case 'inventory':
            // Book inventory summary
            $stmt = $conn->prepare("SELECT 
                                   COUNT(*) as total_books,
                                   SUM(total_copies) as total_copies,
                                   SUM(available_copies) as available_copies,
                                   SUM(total_copies - available_copies) as checked_out_copies
                                   FROM books");
            $stmt->execute();
            $inventory_summary = $stmt->fetch();
            
            // Books by status
            $stmt = $conn->prepare("SELECT 
                                   status, COUNT(*) as copy_count
                                   FROM book_copies
                                   GROUP BY status");
            $stmt->execute();
            $books_by_status = $stmt->fetchAll();
            
            // Books by condition
            $stmt = $conn->prepare("SELECT 
                                   condition, COUNT(*) as copy_count
                                   FROM book_copies
                                   GROUP BY condition");
            $stmt->execute();
            $books_by_condition = $stmt->fetchAll();
            
            // Recently added books
            $stmt = $conn->prepare("SELECT 
                                   b.book_id, b.title, b.isbn, b.total_copies, b.available_copies, b.added_date
                                   FROM books b
                                   WHERE b.added_date BETWEEN ? AND ?
                                   ORDER BY b.added_date DESC
                                   LIMIT 10");
            $stmt->execute([$start_date, $end_date]);
            $recent_books = $stmt->fetchAll();
            break;
            
        case 'requests':
            // Book request summary
            $stmt = $conn->prepare("SELECT 
                                   COUNT(*) as total_requests,
                                   COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
                                   COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_requests,
                                   COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_requests,
                                   COUNT(CASE WHEN status = 'acquired' THEN 1 END) as acquired_requests
                                   FROM book_requests
                                   WHERE request_date BETWEEN ? AND ?");
            $stmt->execute([$start_date, $end_date]);
            $request_summary = $stmt->fetch();
            
            // Daily request statistics
            $stmt = $conn->prepare("SELECT 
                                   DATE(request_date) as date,
                                   COUNT(*) as request_count
                                   FROM book_requests
                                   WHERE request_date BETWEEN ? AND ?
                                   GROUP BY DATE(request_date)
                                   ORDER BY date");
            $stmt->execute([$start_date, $end_date]);
            $daily_requests = $stmt->fetchAll();
            
            // Recent book requests
            $stmt = $conn->prepare("SELECT 
                                   br.request_id, br.title, br.author, br.status, br.request_date,
                                   u.name as user_name, u.email as user_email
                                   FROM book_requests br
                                   JOIN users u ON br.user_id = u.user_id
                                   WHERE br.request_date BETWEEN ? AND ?
                                   ORDER BY br.request_date DESC
                                   LIMIT 10");
            $stmt->execute([$start_date, $end_date]);
            $recent_requests = $stmt->fetchAll();
            break;
    }
} catch(PDOException $e) {
    $error_message = "Error generating report: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="staff.php">
                                <i class="fas fa-user-tie"></i> Staff
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="reports.php">
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
                    <h1 class="h2">Library Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="exportBtn">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                    </div>
                </div>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Report Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-filter me-1"></i> Report Parameters
                    </div>
                    <div class="card-body">
                        <form method="get" action="" class="row g-3">
                            <div class="col-md-4">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="type">
                                    <option value="circulation" <?php echo $report_type == 'circulation' ? 'selected' : ''; ?>>Circulation Report</option>
                                    <option value="users" <?php echo $report_type == 'users' ? 'selected' : ''; ?>>User Activity Report</option>
                                    <option value="fines" <?php echo $report_type == 'fines' ? 'selected' : ''; ?>>Fine Collection Report</option>
                                    <option value="inventory" <?php echo $report_type == 'inventory' ? 'selected' : ''; ?>>Book Inventory Report</option>
                                    <option value="requests" <?php echo $report_type == 'requests' ? 'selected' : ''; ?>>Book Requests Report</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Content -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i> 
                        <?php 
                        $report_title = '';
                        switch ($report_type) {
                            case 'circulation': $report_title = 'Circulation Report'; break;
                            case 'users': $report_title = 'User Activity Report'; break;
                            case 'fines': $report_title = 'Fine Collection Report'; break;
                            case 'inventory': $report_title = 'Book Inventory Report'; break;
                            case 'requests': $report_title = 'Book Requests Report'; break;
                        }
                        echo $report_title;
                        ?>
                        <span class="text-muted ms-2 small">
                            (<?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>)
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if ($report_type == 'circulation'): ?>
                            <!-- Circulation Report -->
                            <div class="row mb-4">
                                <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="card border-left-primary h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Books Borrowed</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $circulation_summary['books_borrowed']; ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-book-reader fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="card border-left-success h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Books Returned</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $circulation_summary['books_returned']; ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="card border-left-warning h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Overdue Books</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $circulation_summary['overdue_books']; ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="card border-left-info h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Fines Collected</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($circulation_summary['collected_fines'], 2); ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Circulation Chart -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="fas fa-chart-line me-1"></i> Daily Circulation Activity
                                        </div>
                                        <div class="card-body">
                                            <canvas id="circulationChart" width="100%" height="30"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Popular Books -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-star me-1"></i> Most Popular Books
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Book ID</th>
                                                    <th>Title</th>
                                                    <th>Times Borrowed</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($popular_books as $book): ?>
                                                <tr>
                                                    <td><?php echo $book['book_id']; ?></td>
                                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                                    <td><?php echo $book['borrow_count']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($report_type == 'users'): ?>
                            <!-- User Activity Report -->
                            <div class="row mb-4">
                                <div class="col-xl-4 col-md-6 mb-4">
                                    <div class="card border-left-primary h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Users</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_summary['active_users']; ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-md-6 mb-4">
                                    <div class="card border-left-success h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Transactions</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_summary['total_transactions']; ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-md-6 mb-4">
                                    <div class="card border-left-info h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg. Transactions Per User</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_summary['avg_transactions_per_user']; ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-calculator fa-2x text-gray-300"></i>
                                                </div>
                                            </div>