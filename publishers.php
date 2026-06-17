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
$publisher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = $error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new publisher
    if (isset($_POST['add_publisher'])) {
        $name = sanitize_input($_POST['name']);
        $address = sanitize_input($_POST['address']);
        $contact_info = sanitize_input($_POST['contact_info']);
        
        try {
            $stmt = $conn->prepare("INSERT INTO publishers (name, address, contact_info) VALUES (?, ?, ?)");
            $stmt->execute([$name, $address, $contact_info]);
            
            $success_message = "Publisher added successfully!";
            $action = 'list'; // Return to list view
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Update existing publisher
    if (isset($_POST['update_publisher'])) {
        $publisher_id = intval($_POST['publisher_id']);
        $name = sanitize_input($_POST['name']);
        $address = sanitize_input($_POST['address']);
        $contact_info = sanitize_input($_POST['contact_info']);
        
        try {
            $stmt = $conn->prepare("UPDATE publishers SET name = ?, address = ?, contact_info = ? WHERE publisher_id = ?");
            $stmt->execute([$name, $address, $contact_info, $publisher_id]);
            
            $success_message = "Publisher updated successfully!";
            $action = 'list'; // Return to list view
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Delete publisher
    if (isset($_POST['delete_publisher'])) {
        $publisher_id = intval($_POST['publisher_id']);
        
        try {
            // Check if publisher has books
            $stmt = $conn->prepare("SELECT COUNT(*) as book_count FROM book_publishers WHERE publisher_id = ?");
            $stmt->execute([$publisher_id]);
            $book_count = $stmt->fetch()['book_count'];
            
            // Check if publisher has periodicals
            $stmt = $conn->prepare("SELECT COUNT(*) as periodical_count FROM periodicals WHERE publisher_id = ?");
            $stmt->execute([$publisher_id]);
            $periodical_count = $stmt->fetch()['periodical_count'];
            
            $total_count = $book_count + $periodical_count;
            
            if ($total_count > 0) {
                $error_message = "Cannot delete publisher. There are $book_count books and $periodical_count periodicals associated with this publisher.";
            } else {
                $stmt = $conn->prepare("DELETE FROM publishers WHERE publisher_id = ?");
                $stmt->execute([$publisher_id]);
                
                $success_message = "Publisher deleted successfully!";
            }
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get publisher details for edit form
if ($action == 'edit' && $publisher_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM publishers WHERE publisher_id = ?");
        $stmt->execute([$publisher_id]);
        $publisher = $stmt->fetch();
        
        if (!$publisher) {
            $error_message = "Publisher not found!";
            $action = 'list';
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get publisher details for view
if ($action == 'view' && $publisher_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM publishers WHERE publisher_id = ?");
        $stmt->execute([$publisher_id]);
        $publisher = $stmt->fetch();
        
        if (!$publisher) {
            $error_message = "Publisher not found!";
            $action = 'list';
        } else {
            // Get publisher's books
            $stmt = $conn->prepare("SELECT b.* FROM books b 
                                   JOIN book_publishers bp ON b.book_id = bp.book_id 
                                   WHERE bp.publisher_id = ? 
                                   ORDER BY b.title");
            $stmt->execute([$publisher_id]);
            $publisher_books = $stmt->fetchAll();
            
            // Get publisher's periodicals
            $stmt = $conn->prepare("SELECT * FROM periodicals WHERE publisher_id = ? ORDER BY title");
            $stmt->execute([$publisher_id]);
            $publisher_periodicals = $stmt->fetchAll();
            
            // Update publication count
            $publication_count = count($publisher_books) + count($publisher_periodicals);
            $stmt = $conn->prepare("UPDATE publishers SET no_of_publications = ? WHERE publisher_id = ?");
            $stmt->execute([$publication_count, $publisher_id]);
            $publisher['no_of_publications'] = $publication_count;
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get all publishers for list view
if ($action == 'list') {
    try {
        // Apply search filter if any
        $where_clause = "";
        $params = [];
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $where_clause = " WHERE name LIKE ? OR address LIKE ? OR contact_info LIKE ?";
            $params = [$search, $search, $search];
        }
        
        $stmt = $conn->prepare("SELECT p.*, 
                               (SELECT COUNT(*) FROM book_publishers bp WHERE bp.publisher_id = p.publisher_id) as book_count,
                               (SELECT COUNT(*) FROM periodicals per WHERE per.publisher_id = p.publisher_id) as periodical_count
                               FROM publishers p $where_clause 
                               ORDER BY p.name");
        $stmt->execute($params);
        $publishers = $stmt->fetchAll();
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
    <title>Publishers - Library Management System</title>
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
                            <a class="nav-link active" href="publishers.php">
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
                    <h1 class="h2">Publishers Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($action == 'list'): ?>
                        <a href="publishers.php?action=add" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add New Publisher
                        </a>
                        <?php else: ?>
                        <a href="publishers.php" class="btn btn-sm btn-secondary">
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
                <!-- Publishers List View -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <i class="fas fa-table me-1"></i>
                                Publishers List
                            </div>
                            <div class="col-md-6">
                                <form action="publishers.php" method="get" class="d-flex">
                                    <input type="hidden" name="action" value="list">
                                    <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search publishers..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Search</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover data-table" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Books</th>
                                        <th>Periodicals</th>
                                        <th>Contact Info</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($publishers) && count($publishers) > 0): ?>
                                        <?php foreach ($publishers as $publisher): ?>
                                        <tr>
                                            <td><?php echo $publisher['publisher_id']; ?></td>
                                            <td><?php echo htmlspecialchars($publisher['name']); ?></td>
                                            <td><?php echo $publisher['book_count']; ?></td>
                                            <td><?php echo $publisher['periodical_count']; ?></td>
                                            <td><?php echo htmlspecialchars($publisher['contact_info'] ?? 'N/A'); ?></td>
                                            <td>
                                                <a href="publishers.php?action=view&id=<?php echo $publisher['publisher_id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="publishers.php?action=edit&id=<?php echo $publisher['publisher_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" title="Delete" 
                                                        onclick="confirmDelete(<?php echo $publisher['publisher_id']; ?>, '<?php echo addslashes($publisher['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No publishers found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete the publisher: <span id="deletePublisherName"></span>?</p>
                                <p class="text-danger">This action cannot be undone!</p>
                            </div>
                            <div class="modal-footer">
                                <form method="post" action="publishers.php">
                                    <input type="hidden" name="publisher_id" id="deletePublisherId">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="delete_publisher" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'add'): ?>
                <!-- Add Publisher Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-plus me-1"></i>
                        Add New Publisher
                    </div>
                    <div class="card-body">
                        <form method="post" action="publishers.php" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                                <div class="invalid-feedback">Please enter a name</div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="contact_info" class="form-label">Contact Information</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info" placeholder="Phone, Email, Website, etc.">
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="publishers.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="add_publisher" class="btn btn-primary">Add Publisher</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'edit' && isset($publisher)): ?>
                <!-- Edit Publisher Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-edit me-1"></i>
                        Edit Publisher: <?php echo htmlspecialchars($publisher['name']); ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="publishers.php" class="needs-validation" novalidate>
                            <input type="hidden" name="publisher_id" value="<?php echo $publisher['publisher_id']; ?>">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($publisher['name']); ?>" required>
                                <div class="invalid-feedback">Please enter a name</div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($publisher['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="contact_info" class="form-label">Contact Information</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info" value="<?php echo htmlspecialchars($publisher['contact_info'] ?? ''); ?>" placeholder="Phone, Email, Website, etc.">
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="publishers.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="update_publisher" class="btn btn-primary">Update Publisher</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'view' && isset($publisher)): ?>
                <!-- View Publisher Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-building me-1"></i>
                        Publisher Details: <?php echo htmlspecialchars($publisher['name']); ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h4><?php echo htmlspecialchars($publisher['name']); ?></h4>
                                
                                <?php if (!empty($publisher['address'])): ?>
                                <div class="mb-3">
                                    <h5>Address</h5>
                                    <p><?php echo nl2br(htmlspecialchars($publisher['address'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h5>Details</h5>
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Contact Info:</th>
                                                <td><?php echo htmlspecialchars($publisher['contact_info'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Total Publications:</th>
                                                <td><?php echo $publisher['no_of_publications'] ?? 0; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Books:</th>
                                                <td><?php echo count($publisher_books ?? []); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Periodicals:</th>
                                                <td><?php echo count($publisher_periodicals ?? []); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-building fa-5x text-primary mb-3"></i>
                                        <div class="d-grid gap-2">
                                            <a href="publishers.php?action=edit&id=<?php echo $publisher['publisher_id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-edit"></i> Edit Publisher
                                            </a>
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="confirmDelete(<?php echo $publisher['publisher_id']; ?>, '<?php echo addslashes($publisher['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete Publisher
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Publisher's Books -->
                        <?php if (isset($publisher_books) && count($publisher_books) > 0): ?>
                        <div class="mt-4">
                            <h5>Books by this Publisher</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>ISBN</th>
                                            <th>Genre</th>
                                            <th>Publication Year</th>
                                            <th>Available Copies</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($publisher_books as $book): ?>
                                        <tr>
                                            <td><?php echo $book['book_id']; ?></td>
                                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                                            <td><?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($book['genre'] ?? 'N/A'); ?></td>
                                            <td><?php echo $book['publication_year'] ?? 'N/A'; ?></td>
                                            <td>
                                                <?php if ($book['available_copies'] > 0): ?>
                                                    <span class="badge bg-success"><?php echo $book['available_copies']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="books.php?action=view&id=<?php echo $book['book_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Publisher's Periodicals -->
                        <?php if (isset($publisher_periodicals) && count($publisher_periodicals) > 0): ?>
                        <div class="mt-4">
                            <h5>Periodicals by this Publisher</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Frequency</th>
                                            <th>Subject</th>
                                            <th>Price</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($publisher_periodicals as $periodical): ?>
                                        <tr>
                                            <td><?php echo $periodical['periodical_id']; ?></td>
                                            <td><?php echo htmlspecialchars($periodical['title']); ?></td>
                                            <td><?php echo htmlspecialchars($periodical['frequency'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($periodical['subject'] ?? 'N/A'); ?></td>
                                            <td>$<?php echo number_format($periodical['price'], 2); ?></td>
                                            <td>
                                                <a href="periodicals.php?action=view&id=<?php echo $periodical['periodical_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ((!isset($publisher_books) || count($publisher_books) == 0) && (!isset($publisher_periodicals) || count($publisher_periodicals) == 0)): ?>
                        <div class="alert alert-info mt-4">
                            No publications found for this publisher.
                        </div>
                        <?php endif; ?>
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
        // Delete confirmation
        function confirmDelete(publisherId, publisherName) {
            document.getElementById('deletePublisherId').value = publisherId;
            document.getElementById('deletePublisherName').textContent = publisherName;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>