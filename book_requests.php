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
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = $error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new book request
    if (isset($_POST['add_request'])) {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : $_SESSION['user_id'];
        $title = sanitize_input($_POST['title']);
        $author = sanitize_input($_POST['author']);
        $publisher = sanitize_input($_POST['publisher']);
        $isbn = sanitize_input($_POST['isbn']);
        $notes = sanitize_input($_POST['notes']);
        
        try {
            // Insert new book request
            $stmt = $conn->prepare("INSERT INTO book_requests (user_id, title, author, publisher, isbn, request_date, status, notes) 
                                   VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'pending', ?)");
            $stmt->execute([$user_id, $title, $author, $publisher, $isbn, $notes]);
            
            $success_message = "Book request submitted successfully!";
            $action = 'list'; // Return to list view
        } catch(Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Update book request status
    if (isset($_POST['update_request'])) {
        $request_id = intval($_POST['request_id']);
        $status = sanitize_input($_POST['status']);
        $notes = sanitize_input($_POST['notes']);
        
        try {
            // Update book request
            $stmt = $conn->prepare("UPDATE book_requests SET 
                                   status = ?, 
                                   notes = ? 
                                   WHERE request_id = ?");
            $stmt->execute([$status, $notes, $request_id]);
            
            $success_message = "Book request updated successfully!";
            $action = 'list'; // Return to list view
        } catch(Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Delete book request
    if (isset($_POST['delete_request'])) {
        $request_id = intval($_POST['request_id']);
        
        try {
            // Delete book request
            $stmt = $conn->prepare("DELETE FROM book_requests WHERE request_id = ?");
            $stmt->execute([$request_id]);
            
            $success_message = "Book request deleted successfully!";
            $action = 'list'; // Return to list view
        } catch(Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get request details for view/edit
if (($action == 'view' || $action == 'edit') && $request_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT br.*, u.name as user_name, u.email as user_email 
                               FROM book_requests br 
                               JOIN users u ON br.user_id = u.user_id 
                               WHERE br.request_id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            $error_message = "Book request not found!";
            $action = 'list';
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get all book requests for list view
if ($action == 'list') {
    try {
        // Apply filters if any
        $where_clause = "";
        $params = [];
        
        // Filter by status if specified
        if (isset($_GET['filter'])) {
            if ($_GET['filter'] == 'pending') {
                $where_clause = " WHERE br.status = 'pending'";
            } elseif ($_GET['filter'] == 'approved') {
                $where_clause = " WHERE br.status = 'approved'";
            } elseif ($_GET['filter'] == 'rejected') {
                $where_clause = " WHERE br.status = 'rejected'";
            } elseif ($_GET['filter'] == 'acquired') {
                $where_clause = " WHERE br.status = 'acquired'";
            } elseif ($_GET['filter'] == 'my_requests') {
                $where_clause = " WHERE br.user_id = ?";
                $params = [$_SESSION['user_id']];
            }
        }
        
        // Search functionality
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            if (empty($where_clause)) {
                $where_clause = " WHERE (br.title LIKE ? OR br.author LIKE ? OR br.publisher LIKE ? OR u.name LIKE ?)"; 
            } else {
                $where_clause .= " AND (br.title LIKE ? OR br.author LIKE ? OR br.publisher LIKE ? OR u.name LIKE ?)"; 
            }
            $params = array_merge($params, [$search, $search, $search, $search]);
        }
        
        $stmt = $conn->prepare("SELECT br.*, u.name as user_name, u.email as user_email 
                               FROM book_requests br 
                               JOIN users u ON br.user_id = u.user_id 
                               $where_clause 
                               ORDER BY br.request_date DESC");
        $stmt->execute($params);
        $requests = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all users for add form
if ($action == 'add' && is_admin()) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE status = 'active' ORDER BY name");
        $stmt->execute();
        $users = $stmt->fetchAll();
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
    <title>Book Requests - Library Management System</title>
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
                            <a class="nav-link active" href="book_requests.php">
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
                    <h1 class="h2">Book Requests</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($action == 'list'): ?>
                        <a href="?action=add" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> New Book Request
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
                <!-- Book Requests List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <i class="fas fa-clipboard-list me-1"></i> Book Requests
                            </div>
                            <div class="col-md-4">
                                <form method="get" action="" class="d-flex">
                                    <input type="hidden" name="action" value="list">
                                    <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="?action=list" class="btn btn-outline-secondary <?php echo !isset($_GET['filter']) ? 'active' : ''; ?>">All Requests</a>
                                <a href="?action=list&filter=pending" class="btn btn-outline-warning <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'pending') ? 'active' : ''; ?>">Pending</a>
                                <a href="?action=list&filter=approved" class="btn btn-outline-success <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'approved') ? 'active' : ''; ?>">Approved</a>
                                <a href="?action=list&filter=rejected" class="btn btn-outline-danger <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'rejected') ? 'active' : ''; ?>">Rejected</a>
                                <a href="?action=list&filter=acquired" class="btn btn-outline-primary <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'acquired') ? 'active' : ''; ?>">Acquired</a>
                                <a href="?action=list&filter=my_requests" class="btn btn-outline-info <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'my_requests') ? 'active' : ''; ?>">My Requests</a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="requestsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Requested By</th>
                                        <th>Request Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($requests) && count($requests) > 0): ?>
                                        <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td><?php echo $request['request_id']; ?></td>
                                            <td><?php echo htmlspecialchars($request['title']); ?></td>
                                            <td><?php echo htmlspecialchars($request['author']); ?></td>
                                            <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($request['request_date'])); ?></td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                switch($request['status']) {
                                                    case 'pending':
                                                        $status_class = 'bg-warning text-dark';
                                                        break;
                                                    case 'approved':
                                                        $status_class = 'bg-success';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'bg-danger';
                                                        break;
                                                    case 'acquired':
                                                        $status_class = 'bg-primary';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?action=view&id=<?php echo $request['request_id']; ?>" class="btn btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (is_admin() || $_SESSION['user_id'] == $request['user_id']): ?>
                                                    <a href="?action=edit&id=<?php echo $request['request_id']; ?>" class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $request['request_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $request['request_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to delete this book request: <strong><?php echo htmlspecialchars($request['title']); ?></strong>?
                                                                <p class="text-danger">This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="post" action="">
                                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                                    <button type="submit" name="delete_request" class="btn btn-danger">Delete</button>
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
                                            <td colspan="7" class="text-center">No book requests found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($action == 'add'): ?>
                <!-- Add Book Request Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-plus me-1"></i> New Book Request
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if (is_admin() && isset($users)): ?>
                            <div class="mb-3">
                                <label for="user_id" class="form-label">Request For User</label>
                                <select class="form-select" id="user_id" name="user_id" required>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the user who is requesting this book.</div>
                            </div>
                            <?php endif; ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label">Book Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="author" class="form-label">Author</label>
                                    <input type="text" class="form-control" id="author" name="author">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="publisher" name="publisher">
                                </div>
                                <div class="col-md-6">
                                    <label for="isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Why do you need this book? Any additional information..."></textarea>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="add_request" class="btn btn-primary">Submit Request</button>
                                <a href="?action=list" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php elseif ($action == 'view' && isset($request)): ?>
                <!-- View Book Request Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-clipboard-check me-1"></i> Book Request Details
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Request Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Request ID</th>
                                        <td><?php echo $request['request_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Book Title</th>
                                        <td><?php echo htmlspecialchars($request['title']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Author</th>
                                        <td><?php echo htmlspecialchars($request['author']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Publisher</th>
                                        <td><?php echo htmlspecialchars($request['publisher']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>ISBN</th>
                                        <td><?php echo htmlspecialchars($request['isbn']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Request Date</th>
                                        <td><?php echo date('Y-m-d', strtotime($request['request_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <?php 
                                            $status_class = '';
                                            switch($request['status']) {
                                                case 'pending':
                                                    $status_class = 'bg-warning text-dark';
                                                    break;
                                                case 'approved':
                                                    $status_class = 'bg-success';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'bg-danger';
                                                    break;
                                                case 'acquired':
                                                    $status_class = 'bg-primary';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Notes</th>
                                        <td><?php echo nl2br(htmlspecialchars($request['notes'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Requester Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Name</th>
                                        <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td><?php echo htmlspecialchars($request['user_email']); ?></td>
                                    </tr>
                                </table>
                                
                                <?php if (is_admin()): ?>
                                <!-- Admin Actions -->
                                <h5 class="mt-4">Update Request Status</h5>
                                <form method="post" action="">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="pending" <?php echo $request['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $request['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $request['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="acquired" <?php echo $request['status'] == 'acquired' ? 'selected' : ''; ?>>Acquired</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_notes" class="form-label">Admin Notes</label>
                                        <textarea class="form-control" id="admin_notes" name="notes" rows="3"><?php echo htmlspecialchars($request['notes']); ?></textarea>
                                    </div>
                                    <button type="submit" name="update_request" class="btn btn-primary">Update Status</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-4">
                            <?php if (is_admin() || $_SESSION['user_id'] == $request['user_id']): ?>
                            <a href="?action=edit&id=<?php echo $request['request_id']; ?>" class="btn btn-primary">Edit Request</a>
                            <?php endif; ?>
                            <a href="?action=list" class="btn btn-secondary">Back to List</a>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($action == 'edit' && isset($request)): ?>
                <!-- Edit Book Request Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-edit me-1"></i> Edit Book Request
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label">Book Title</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($request['title']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="author" class="form-label">Author</label>
                                    <input type="text" class="form-control" id="author" name="author" value="<?php echo htmlspecialchars($request['author']); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="publisher" name="publisher" value="<?php echo htmlspecialchars($request['publisher']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($request['isbn']); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($request['notes']); ?></textarea>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="update_request" class="btn btn-primary">Update Request</button>
                                <a href="?action=list" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
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
    <!-- Custom JS -->
    <script src="js/script.js"></script>
    <script>
        $(document).ready(function() {
            $('#requestsTable').DataTable({
                "pageLength": 25,
                "order": [[ 0, "desc" ]],
                "language": {
                    "search": "Quick search:",
                    "lengthMenu": "Show _MENU_ requests per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ requests",
                    "infoEmpty": "Showing 0 to 0 of 0 requests",
                    "infoFiltered": "(filtered from _MAX_ total requests)"
                }
            });
        });
    </script>
</body>
</html>