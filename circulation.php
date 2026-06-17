<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$lending_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$success_message = $error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Issue book
    if (isset($_POST['issue_book'])) {
        $copy_id = intval($_POST['copy_id']);
        $user_id = intval($_POST['user_id']);
        $due_date = sanitize_input($_POST['due_date']);
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Check if copy is available
            $stmt = $conn->prepare("SELECT status, book_id FROM book_copies WHERE copy_id = ?");
            $stmt->execute([$copy_id]);
            $copy = $stmt->fetch();
            
            if (!$copy) {
                throw new Exception("Book copy not found!");
            }
            
            if ($copy['status'] != 'available') {
                throw new Exception("This book copy is not available for lending!");
            }
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("User not found!");
            }
            
            // Check if user has any overdue books
            $stmt = $conn->prepare("SELECT COUNT(*) as overdue_count FROM book_lending 
                                   WHERE user_id = ? AND due_date < CURDATE() AND return_date IS NULL");
            $stmt->execute([$user_id]);
            $overdue_count = $stmt->fetch()['overdue_count'];
            
            if ($overdue_count > 0) {
                throw new Exception("User has $overdue_count overdue books. Cannot issue new books until overdue books are returned.");
            }
            
            // Check if user has reached maximum allowed books
            $stmt = $conn->prepare("SELECT COUNT(*) as current_books FROM book_lending 
                                   WHERE user_id = ? AND return_date IS NULL");
            $stmt->execute([$user_id]);
            $current_books = $stmt->fetch()['current_books'];
            
            $max_books = 5; // Maximum books a user can borrow at once
            if ($current_books >= $max_books) {
                throw new Exception("User has already borrowed $current_books books. Maximum allowed is $max_books.");
            }
            
            // Insert lending record
            $stmt = $conn->prepare("INSERT INTO book_lending (copy_id, user_id, issue_date, due_date, status) 
                                   VALUES (?, ?, CURRENT_TIMESTAMP, ?, 'borrowed')");
            $stmt->execute([$copy_id, $user_id, $due_date]);
            
            // Update book copy status
            $stmt = $conn->prepare("UPDATE book_copies SET status = 'borrowed' WHERE copy_id = ?");
            $stmt->execute([$copy_id]);
            
            // Update book available copies count
            $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?");
            $stmt->execute([$copy['book_id']]);
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Book issued successfully!";
            $action = 'list'; // Return to list view
        } catch(Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Return book
    if (isset($_POST['return_book'])) {
        $lending_id = intval($_POST['lending_id']);
        $fine_paid = isset($_POST['fine_paid']) ? 1 : 0;
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Get lending details
            $stmt = $conn->prepare("SELECT bl.*, bc.book_id, bl.due_date 
                                   FROM book_lending bl 
                                   JOIN book_copies bc ON bl.copy_id = bc.copy_id 
                                   WHERE bl.lending_id = ?");
            $stmt->execute([$lending_id]);
            $lending = $stmt->fetch();
            
            if (!$lending) {
                throw new Exception("Lending record not found!");
            }
            
            if ($lending['return_date'] !== null) {
                throw new Exception("This book has already been returned!");
            }
            
            // Calculate fine if overdue
            $due_date = new DateTime($lending['due_date']);
            $today = new DateTime();
            $fine_amount = 0;
            
            if ($today > $due_date) {
                $diff = $today->diff($due_date);
                $days_overdue = $diff->days;
                $fine_amount = $days_overdue * 1; // $1 per day overdue
            }
            
            // Update lending record
            $stmt = $conn->prepare("UPDATE book_lending SET 
                                   return_date = CURRENT_TIMESTAMP, 
                                   fine_amount = ?, 
                                   fine_paid = ?, 
                                   status = 'returned' 
                                   WHERE lending_id = ?");
            $stmt->execute([$fine_amount, $fine_paid, $lending_id]);
            
            // Update book copy status
            $stmt = $conn->prepare("UPDATE book_copies SET status = 'available' WHERE copy_id = ?");
            $stmt->execute([$lending['copy_id']]);
            
            // Update book available copies count
            $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
            $stmt->execute([$lending['book_id']]);
            
            // Update library statistics
            $today_date = date('Y-m-d');
            $stmt = $conn->prepare("SELECT * FROM library_statistics WHERE stat_date = ?");
            $stmt->execute([$today_date]);
            $stats = $stmt->fetch();
            
            if ($stats) {
                $stmt = $conn->prepare("UPDATE library_statistics SET 
                                       books_returned = books_returned + 1, 
                                       fines_collected = fines_collected + ? 
                                       WHERE stat_date = ?");
                $stmt->execute([$fine_paid ? $fine_amount : 0, $today_date]);
            } else {
                $stmt = $conn->prepare("INSERT INTO library_statistics 
                                       (stat_date, books_returned, fines_collected) 
                                       VALUES (?, 1, ?)");
                $stmt->execute([$today_date, $fine_paid ? $fine_amount : 0]);
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Book returned successfully!" . ($fine_amount > 0 ? " Fine amount: $" . $fine_amount : "");
            $action = 'list'; // Return to list view
        } catch(Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Renew book
    if (isset($_POST['renew_book'])) {
        $lending_id = intval($_POST['lending_id']);
        $new_due_date = sanitize_input($_POST['new_due_date']);
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Get lending details
            $stmt = $conn->prepare("SELECT * FROM book_lending WHERE lending_id = ?");
            $stmt->execute([$lending_id]);
            $lending = $stmt->fetch();
            
            if (!$lending) {
                throw new Exception("Lending record not found!");
            }
            
            if ($lending['return_date'] !== null) {
                throw new Exception("This book has already been returned!");
            }
            
            // Check if book is overdue
            $due_date = new DateTime($lending['due_date']);
            $today = new DateTime();
            
            if ($today > $due_date) {
                throw new Exception("This book is overdue. It must be returned before it can be renewed.");
            }
            
            // Check if book has been renewed too many times
            $max_renewals = 2; // Maximum number of renewals allowed
            if ($lending['renewed_times'] >= $max_renewals) {
                throw new Exception("This book has already been renewed $lending[renewed_times] times. Maximum allowed is $max_renewals.");
            }
            
            // Update lending record
            $stmt = $conn->prepare("UPDATE book_lending SET 
                                   due_date = ?, 
                                   renewed_times = renewed_times + 1 
                                   WHERE lending_id = ?");
            $stmt->execute([$new_due_date, $lending_id]);
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Book renewed successfully! New due date: " . date('Y-m-d', strtotime($new_due_date));
            $action = 'list'; // Return to list view
        } catch(Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get lending details for view
if ($action == 'view' && $lending_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT bl.*, 
                               b.title as book_title, b.book_id, 
                               u.name as user_name, u.email as user_email, 
                               bc.copy_id, bc.condition, bc.location 
                               FROM book_lending bl 
                               JOIN book_copies bc ON bl.copy_id = bc.copy_id 
                               JOIN books b ON bc.book_id = b.book_id 
                               JOIN users u ON bl.user_id = u.user_id 
                               WHERE bl.lending_id = ?");
        $stmt->execute([$lending_id]);
        $lending = $stmt->fetch();
        
        if (!$lending) {
            $error_message = "Lending record not found!";
            $action = 'list';
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get all lendings for list view
if ($action == 'list') {
    try {
        // Apply filters if any
        $where_clause = "";
        $params = [];
        
        if (isset($_GET['filter'])) {
            if ($_GET['filter'] == 'borrowed') {
                $where_clause = " WHERE bl.return_date IS NULL";
            } elseif ($_GET['filter'] == 'returned') {
                $where_clause = " WHERE bl.return_date IS NOT NULL";
            } elseif ($_GET['filter'] == 'overdue') {
                $where_clause = " WHERE bl.due_date < CURDATE() AND bl.return_date IS NULL";
            }
        }
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            if (empty($where_clause)) {
                $where_clause = " WHERE (b.title LIKE ? OR u.name LIKE ? OR u.email LIKE ?)"; 
            } else {
                $where_clause .= " AND (b.title LIKE ? OR u.name LIKE ? OR u.email LIKE ?)"; 
            }
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        $stmt = $conn->prepare("SELECT bl.*, 
                               b.title as book_title, 
                               u.name as user_name, u.email as user_email 
                               FROM book_lending bl 
                               JOIN book_copies bc ON bl.copy_id = bc.copy_id 
                               JOIN books b ON bc.book_id = b.book_id 
                               JOIN users u ON bl.user_id = u.user_id 
                               $where_clause 
                               ORDER BY bl.issue_date DESC");
        $stmt->execute($params);
        $lendings = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get available book copies for issue form
if ($action == 'issue') {
    try {
        // If book_id is provided, get only copies of that book
        if ($book_id > 0) {
            $stmt = $conn->prepare("SELECT bc.*, b.title 
                                   FROM book_copies bc 
                                   JOIN books b ON bc.book_id = b.book_id 
                                   WHERE bc.status = 'available' AND bc.book_id = ? 
                                   ORDER BY bc.copy_id");
            $stmt->execute([$book_id]);
        } else {
            $stmt = $conn->prepare("SELECT bc.*, b.title 
                                   FROM book_copies bc 
                                   JOIN books b ON bc.book_id = b.book_id 
                                   WHERE bc.status = 'available' 
                                   ORDER BY b.title, bc.copy_id");
            $stmt->execute();
        }
        $available_copies = $stmt->fetchAll();
        
        // Get all users
        $stmt = $conn->prepare("SELECT * FROM users WHERE status = 'active' ORDER BY name");
        $stmt->execute();
        $users = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get borrowed books for return form
if ($action == 'return') {
    try {
        // If user_id is provided, get only books borrowed by that user
        if ($user_id > 0) {
            $stmt = $conn->prepare("SELECT bl.*, 
                                   b.title as book_title, 
                                   u.name as user_name, u.email as user_email 
                                   FROM book_lending bl 
                                   JOIN book_copies bc ON bl.copy_id = bc.copy_id 
                                   JOIN books b ON bc.book_id = b.book_id 
                                   JOIN users u ON bl.user_id = u.user_id 
                                   WHERE bl.return_date IS NULL AND bl.user_id = ? 
                                   ORDER BY bl.due_date");
            $stmt->execute([$user_id]);
        } else {
            $stmt = $conn->prepare("SELECT bl.*, 
                                   b.title as book_title, 
                                   u.name as user_name, u.email as user_email 
                                   FROM book_lending bl 
                                   JOIN book_copies bc ON bl.copy_id = bc.copy_id 
                                   JOIN books b ON bc.book_id = b.book_id 
                                   JOIN users u ON bl.user_id = u.user_id 
                                   WHERE bl.return_date IS NULL 
                                   ORDER BY bl.due_date");
            $stmt->execute();
        }
        $borrowed_books = $stmt->fetchAll();
        
        // Get all users with borrowed books
        $stmt = $conn->prepare("SELECT DISTINCT u.* 
                               FROM users u 
                               JOIN book_lending bl ON u.user_id = bl.user_id 
                               WHERE bl.return_date IS NULL 
                               ORDER BY u.name");
        $stmt->execute();
        $borrowers = $stmt->fetchAll();
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
    <title>Circulation - Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Select2 CSS for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
                            <a class="nav-link active" href="circulation.php">
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
                    <h1 class="h2">Circulation Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="circulation.php?action=issue" class="btn btn-sm btn-success">
                                <i class="fas fa-paper-plane"></i> Issue Book
                            </a>
                            <a href="circulation.php?action=return" class="btn btn-sm btn-warning">
                                <i class="fas fa-undo"></i> Return Book
                            </a>
                        </div>
                        <?php if ($action != 'list'): ?>
                        <a href="circulation.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>

                <?php if ($action == 'list'): ?>
                <!-- Circulation List View -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <i class="fas fa-table me-1"></i>
                                Book Lending Records
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-end">
                                    <div class="btn-group me-2">
                                        <a href="circulation.php" class="btn btn-sm btn-outline-secondary <?php echo !isset($_GET['filter']) ? 'active' : ''; ?>">All</a>
                                        <a href="circulation.php?filter=borrowed" class="btn btn-sm btn-outline-secondary <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'borrowed') ? 'active' : ''; ?>">Borrowed</a>
                                        <a href="circulation.php?filter=returned" class="btn btn-sm btn-outline-secondary <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'returned') ? 'active' : ''; ?>">Returned</a>
                                        <a href="circulation.php?filter=overdue" class="btn btn-sm btn-outline-secondary <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'overdue') ? 'active' : ''; ?>">Overdue</a>
                                    </div>
                                    <form action="circulation.php" method="get" class="d-flex ms-2">
                                        <?php if (isset($_GET['filter'])): ?>
                                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($_GET['filter']); ?>">
                                        <?php endif; ?>
                                        <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Search</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover data-table" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Book</th>
                                        <th>User</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Return Date</th>
                                        <th>Status</th>
                                        <th>Fine</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($lendings) && count($lendings) > 0): ?>
                                        <?php foreach ($lendings as $lending): ?>
                                        <tr>
                                            <td><?php echo $lending['lending_id']; ?></td>
                                            <td><?php echo htmlspecialchars($lending['book_title']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($lending['user_name']); ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($lending['user_email']); ?></small>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($lending['issue_date'])); ?></td>
                                            <td>
                                                <?php 
                                                $due_date = new DateTime($lending['due_date']);
                                                $today = new DateTime();
                                                $is_overdue = ($today > $due_date && $lending['return_date'] === null);
                                                ?>
                                                <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                    <?php echo date('Y-m-d', strtotime($lending['due_date'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($lending['return_date']): ?>
                                                    <?php echo date('Y-m-d', strtotime($lending['return_date'])); ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not Returned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($lending['return_date']): ?>
                                                    <span class="badge bg-success">Returned</span>
                                                <?php elseif ($is_overdue): ?>
                                                    <span class="badge bg-danger">Overdue</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Borrowed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($lending['fine_amount'] > 0): ?>
                                                    $<?php echo number_format($lending['fine_amount'], 2); ?>
                                                    <?php if ($lending['fine_paid']): ?>
                                                        <span class="badge bg-success">Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Unpaid</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="circulation.php?action=view&id=<?php echo $lending['lending_id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (!$lending['return_date']): ?>
                                                    <a href="circulation.php?action=return&id=<?php echo $lending['lending_id']; ?>" class="btn btn-sm btn-warning" title="Return">
                                                        <i class="fas fa-undo"></i>
                                                    </a>
                                                    <?php if (!$is_overdue && $lending['renewed_times'] < 2): ?>
                                                    <button type="button" class="btn btn-sm btn-success" title="Renew" 
                                                            onclick="openRenewModal(<?php echo $lending['lending_id']; ?>, '<?php echo addslashes($lending['book_title']); ?>', '<?php echo $lending['due_date']; ?>')">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No lending records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Renew Book Modal -->
                <div class="modal fade" id="renewModal" tabindex="-1" aria-labelledby="renewModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="renewModalLabel">Renew Book</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" action="circulation.php">
                                <div class="modal-body">
                                    <input type="hidden" name="lending_id" id="renewLendingId">
                                    <p>You are renewing: <span id="renewBookTitle" class="fw-bold"></span></p>
                                    <p>Current due date: <span id="renewCurrentDueDate"></span></p>
                                    <div class="mb-3">
                                        <label for="new_due_date" class="form-label">New Due Date</label>
                                        <input type="date" class="form-control" id="new_due_date" name="new_due_date" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="renew_book" class="btn btn-success">Renew Book</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'issue'): ?>
                <!-- Issue Book Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-paper-plane me-1"></i>
                        Issue Book
                    </div>
                    <div class="card-body">
                        <?php if (isset($available_copies) && count($available_copies) > 0): ?>
                        <form method="post" action="circulation.php" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="copy_id" class="form-label">Select Book Copy</label>
                                <select class="form-control select2" id="copy_id" name="copy_id" required>
                                    <option value="">Select Book Copy</option>
                                    <?php foreach ($available_copies as $copy): ?>
                                    <option value="<?php echo $copy['copy_id']; ?>">
                                        <?php echo htmlspecialchars($copy['title']); ?> 
                                        (Copy ID: <?php echo $copy['copy_id']; ?>, 
                                        Condition: <?php echo ucfirst($copy['condition']); ?>, 
                                        Location: <?php echo $copy['location']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a book copy</div>
                            </div>
                            <div class="mb-3">
                                <label for="user_id" class="form-label">Select User</label>
                                <select class="form-control select2" id="user_id" name="user_id" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>" <?php echo ($user_id == $user['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?> 
                                        (<?php echo htmlspecialchars($user['email']); ?>, 
                                        Type: <?php echo ucfirst($user['user_type']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a user</div>
                            </div>
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <?php 
                                // Default due date is 14 days from today
                                $default_due_date = date('Y-m-d', strtotime('+14 days'));
                                ?>
                                <input type="date" class="form-control" id="due_date" name="due_date" 
                                       value="<?php echo $default_due_date; ?>" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                <div class="invalid-feedback">Please select a due date</div>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="circulation.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="issue_book" class="btn btn-success">Issue Book</button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No available book copies found. All books are currently borrowed or not available.
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="circulation.php" class="btn btn-secondary">Back to List</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($action == 'return'): ?>
                <!-- Return Book Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-undo me-1"></i>
                        Return Book
                    </div>
                    <div class="card-body">
                        <?php if (isset($borrowed_books) && count($borrowed_books) > 0): ?>
                        <div class="mb-3">
                            <label for="borrower_filter" class="form-label">Filter by User</label>
                            <select class="form-control select2" id="borrower_filter" onchange="filterByUser(this.value)">
                                <option value="">All Users</option>
                                <?php foreach ($borrowers as $borrower): ?>
                                <option value="<?php echo $borrower['user_id']; ?>" <?php echo ($user_id == $borrower['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($borrower['name']); ?> 
                                    (<?php echo htmlspecialchars($borrower['email']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="borrowedBooksTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Book</th>
                                        <th>User</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrowed_books as $book): ?>
                                    <?php 
                                    $due_date = new DateTime($book['due_date']);
                                    $today = new DateTime();
                                    $is_overdue = ($today > $due_date);
                                    ?>
                                    <tr>
                                        <td><?php echo $book['lending_id']; ?></td>
                                        <td><?php echo htmlspecialchars($book['book_title']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($book['user_name']); ?>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($book['user_email']); ?></small>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($book['issue_date'])); ?></td>
                                        <td>
                                            <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo date('Y-m-d', strtotime($book['due_date'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($is_overdue): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Borrowed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick="openReturnModal(<?php echo $book['lending_id']; ?>, '<?php echo addslashes($book['book_title']); ?>', '<?php echo $book['due_date']; ?>', <?php echo $is_overdue ? 'true' : 'false'; ?>)">
                                                <i class="fas fa-undo"></i> Return
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No borrowed books found. All books have been returned.
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="circulation.php" class="btn btn-secondary">Back to List</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Return Book Modal -->
                <div class="modal fade" id="returnModal" tabindex="-1" aria-labelledby="returnModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title" id="returnModalLabel">Return Book</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" action="circulation.php">
                                <div class="modal-body">
                                    <input type="hidden" name="lending_id" id="returnLendingId">
                                    <p>You are returning: <span id="returnBookTitle" class="fw-bold"></span></p>
                                    <div id="fineSection" style="display: none;">
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            This book is overdue! Due date was: <span id="returnDueDate"></span>
                                            <div class="mt-2">
                                                <strong>Fine Amount: $<span id="fineAmount">0.00</span></strong>
                                            </div>
                                        </div>
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" id="fine_paid" name="fine_paid">
                                            <label class="form-check-label" for="fine_paid">Fine has been paid</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="return_book" class="btn btn-warning">Return Book</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'view' && isset($lending)): ?>
                <!-- View Lending Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Lending Record Details
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Book Information</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th>Title:</th>
                                        <td><?php echo htmlspecialchars($lending['book_title']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Book ID:</th>
                                        <td><?php echo $lending['book_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Copy ID:</th>
                                        <td><?php echo $lending['copy_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Condition:</th>
                                        <td><?php echo ucfirst($lending['condition']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Location:</th>
                                        <td><?php echo htmlspecialchars($lending['location']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>User Information</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th>Name:</th>
                                        <td><?php echo htmlspecialchars($lending['user_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo htmlspecialchars($lending['user_email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>User ID:</th>
                                        <td><?php echo $lending['user_id']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>Lending Details</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th>Lending ID:</th>
                                        <td><?php echo $lending['lending_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Issue Date:</th>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($lending['issue_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Due Date:</th>
                                        <td>
                                            <?php 
                                            $due_date = new DateTime($lending['due_date']);
                                            $today = new DateTime();
                                            $is_overdue = ($today > $due_date && $lending['return_date'] === null);
                                            ?>
                                            <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo date('Y-m-d', strtotime($lending['due_date'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Return Date:</th>
                                        <td>
                                            <?php if ($lending['return_date']): ?>
                                                <?php echo date('Y-m-d H:i:s', strtotime($lending['return_date'])); ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Returned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <?php if ($lending['return_date']): ?>
                                                <span class="badge bg-success">Returned</span>
                                            <?php elseif ($is_overdue): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Borrowed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Renewed Times:</th>
                                        <td><?php echo $lending['renewed_times']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Fine Amount:</th>
                                        <td>
                                            <?php if ($lending['fine_amount'] > 0): ?>
                                                $<?php echo number_format($lending['fine_amount'], 2); ?>
                                                <?php if ($lending['fine_paid']): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Unpaid</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                $0.00
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                            <a href="circulation.php" class="btn btn-secondary">Back to List</a>
                            
                            <?php if (!$lending['return_date']): ?>
                                <a href="circulation.php?action=return&id=<?php echo $lending['lending_id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-undo"></i> Return Book
                                </a>
                                <?php if (!$is_overdue && $lending['renewed_times'] < 2): ?>
                                <button type="button" class="btn btn-success" 
                                        onclick="openRenewModal(<?php echo $lending['lending_id']; ?>, '<?php echo addslashes($lending['book_title']); ?>', '<?php echo $lending['due_date']; ?>')">
                                    <i class="fas fa-sync-alt"></i> Renew Book
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Custom JS -->
    <script src="js/script.js"></script>
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2();
            
            // Initialize DataTable
            $('.data-table').DataTable({
                "order": [[0, "desc"]],
                "pageLength": 25
            });
        });
        
        // Open renew modal
        function openRenewModal(lendingId, bookTitle, dueDate) {
            document.getElementById('renewLendingId').value = lendingId;
            document.getElementById('renewBookTitle').textContent = bookTitle;
            document.getElementById('renewCurrentDueDate').textContent = dueDate;
            
            // Set default new due date (14 days from current due date)
            const currentDueDate = new Date(dueDate);
            currentDueDate.setDate(currentDueDate.getDate() + 14);
            document.getElementById('new_due_date').valueAsDate = currentDueDate;
            document.getElementById('new_due_date').min = new Date().toISOString().split('T')[0]; // Today
            
            const renewModal = new bootstrap.Modal(document.getElementById('renewModal'));
            renewModal.show();
        }
        
        // Open return modal
        function openReturnModal(lendingId, bookTitle, dueDate, isOverdue) {
            document.getElementById('returnLendingId').value = lendingId;
            document.getElementById('returnBookTitle').textContent = bookTitle;
            
            if (isOverdue) {
                document.getElementById('fineSection').style.display = 'block';
                document.getElementById('returnDueDate').textContent = dueDate;
                
                // Calculate fine amount ($1 per day overdue)
                const dueDateTime = new Date(dueDate);
                const today = new Date();
                const diffTime = Math.abs(today - dueDateTime);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                document.getElementById('fineAmount').textContent = diffDays.toFixed(2);
            } else {
                document.getElementById('fineSection').style.display = 'none';
            }
            
            const returnModal = new bootstrap.Modal(document.getElementById('returnModal'));
            returnModal.show();
        }
        
        // Filter borrowed books by user
        function filterByUser(userId) {
            if (userId) {
                window.location.href = 'circulation.php?action=return&user_id=' + userId;
            } else {
                window.location.href = 'circulation.php?action=return';
            }
        }
    </script>
</body>
</html>