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
$author_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = $error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new author
    if (isset($_POST['add_author'])) {
        $name = sanitize_input($_POST['name']);
        $biography = sanitize_input($_POST['biography']);
        $contact_info = sanitize_input($_POST['contact_info']);
        
        try {
            $stmt = $conn->prepare("INSERT INTO authors (name, biography, contact_info) VALUES (?, ?, ?)");
            $stmt->execute([$name, $biography, $contact_info]);
            
            $success_message = "Author added successfully!";
            $action = 'list'; // Return to list view
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Update existing author
    if (isset($_POST['update_author'])) {
        $author_id = intval($_POST['author_id']);
        $name = sanitize_input($_POST['name']);
        $biography = sanitize_input($_POST['biography']);
        $contact_info = sanitize_input($_POST['contact_info']);
        
        try {
            $stmt = $conn->prepare("UPDATE authors SET name = ?, biography = ?, contact_info = ? WHERE author_id = ?");
            $stmt->execute([$name, $biography, $contact_info, $author_id]);
            
            $success_message = "Author updated successfully!";
            $action = 'list'; // Return to list view
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Delete author
    if (isset($_POST['delete_author'])) {
        $author_id = intval($_POST['author_id']);
        
        try {
            // Check if author has books
            $stmt = $conn->prepare("SELECT COUNT(*) as book_count FROM book_authors WHERE author_id = ?");
            $stmt->execute([$author_id]);
            $book_count = $stmt->fetch()['book_count'];
            
            if ($book_count > 0) {
                $error_message = "Cannot delete author. There are $book_count books associated with this author.";
            } else {
                $stmt = $conn->prepare("DELETE FROM authors WHERE author_id = ?");
                $stmt->execute([$author_id]);
                
                $success_message = "Author deleted successfully!";
            }
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get author details for edit form
if ($action == 'edit' && $author_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM authors WHERE author_id = ?");
        $stmt->execute([$author_id]);
        $author = $stmt->fetch();
        
        if (!$author) {
            $error_message = "Author not found!";
            $action = 'list';
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get author details for view
if ($action == 'view' && $author_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM authors WHERE author_id = ?");
        $stmt->execute([$author_id]);
        $author = $stmt->fetch();
        
        if (!$author) {
            $error_message = "Author not found!";
            $action = 'list';
        } else {
            // Get author's books
            $stmt = $conn->prepare("SELECT b.* FROM books b 
                                   JOIN book_authors ba ON b.book_id = ba.book_id 
                                   WHERE ba.author_id = ? 
                                   ORDER BY b.title");
            $stmt->execute([$author_id]);
            $author_books = $stmt->fetchAll();
            
            // Update publication count
            $publication_count = count($author_books);
            $stmt = $conn->prepare("UPDATE authors SET no_of_publications = ? WHERE author_id = ?");
            $stmt->execute([$publication_count, $author_id]);
            $author['no_of_publications'] = $publication_count;
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get all authors for list view
if ($action == 'list') {
    try {
        // Apply search filter if any
        $where_clause = "";
        $params = [];
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $where_clause = " WHERE name LIKE ? OR biography LIKE ?";
            $params = [$search, $search];
        }
        
        $stmt = $conn->prepare("SELECT a.*, 
                               (SELECT COUNT(*) FROM book_authors ba WHERE ba.author_id = a.author_id) as book_count 
                               FROM authors a $where_clause 
                               ORDER BY a.name");
        $stmt->execute($params);
        $authors = $stmt->fetchAll();
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
    <title>Authors - Library Management System</title>
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
                            <a class="nav-link active" href="authors.php">
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
                    <h1 class="h2">Authors Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($action == 'list'): ?>
                        <a href="authors.php?action=add" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add New Author
                        </a>
                        <?php else: ?>
                        <a href="authors.php" class="btn btn-sm btn-secondary">
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
                <!-- Authors List View -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <i class="fas fa-table me-1"></i>
                                Authors List
                            </div>
                            <div class="col-md-6">
                                <form action="authors.php" method="get" class="d-flex">
                                    <input type="hidden" name="action" value="list">
                                    <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search authors..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
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
                                        <th>Publications</th>
                                        <th>Contact Info</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($authors) && count($authors) > 0): ?>
                                        <?php foreach ($authors as $author): ?>
                                        <tr>
                                            <td><?php echo $author['author_id']; ?></td>
                                            <td><?php echo htmlspecialchars($author['name']); ?></td>
                                            <td><?php echo $author['book_count']; ?></td>
                                            <td><?php echo htmlspecialchars($author['contact_info'] ?? 'N/A'); ?></td>
                                            <td>
                                                <a href="authors.php?action=view&id=<?php echo $author['author_id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="authors.php?action=edit&id=<?php echo $author['author_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" title="Delete" 
                                                        onclick="confirmDelete(<?php echo $author['author_id']; ?>, '<?php echo addslashes($author['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No authors found</td>
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
                                <p>Are you sure you want to delete the author: <span id="deleteAuthorName"></span>?</p>
                                <p class="text-danger">This action cannot be undone!</p>
                            </div>
                            <div class="modal-footer">
                                <form method="post" action="authors.php">
                                    <input type="hidden" name="author_id" id="deleteAuthorId">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="delete_author" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'add'): ?>
                <!-- Add Author Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-plus me-1"></i>
                        Add New Author
                    </div>
                    <div class="card-body">
                        <form method="post" action="authors.php" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                                <div class="invalid-feedback">Please enter a name</div>
                            </div>
                            <div class="mb-3">
                                <label for="biography" class="form-label">Biography</label>
                                <textarea class="form-control" id="biography" name="biography" rows="4"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="contact_info" class="form-label">Contact Information</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info">
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="authors.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="add_author" class="btn btn-primary">Add Author</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'edit' && isset($author)): ?>
                <!-- Edit Author Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-edit me-1"></i>
                        Edit Author: <?php echo htmlspecialchars($author['name']); ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="authors.php" class="needs-validation" novalidate>
                            <input type="hidden" name="author_id" value="<?php echo $author['author_id']; ?>">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($author['name']); ?>" required>
                                <div class="invalid-feedback">Please enter a name</div>
                            </div>
                            <div class="mb-3">
                                <label for="biography" class="form-label">Biography</label>
                                <textarea class="form-control" id="biography" name="biography" rows="4"><?php echo htmlspecialchars($author['biography'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="contact_info" class="form-label">Contact Information</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info" value="<?php echo htmlspecialchars($author['contact_info'] ?? ''); ?>">
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="authors.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="update_author" class="btn btn-primary">Update Author</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'view' && isset($author)): ?>
                <!-- View Author Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-edit me-1"></i>
                        Author Details: <?php echo htmlspecialchars($author['name']); ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h4><?php echo htmlspecialchars($author['name']); ?></h4>
                                
                                <?php if (!empty($author['biography'])): ?>
                                <div class="mb-3">
                                    <h5>Biography</h5>
                                    <p><?php echo nl2br(htmlspecialchars($author['biography'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h5>Details</h5>
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Contact Info:</th>
                                                <td><?php echo htmlspecialchars($author['contact_info'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Publications:</th>
                                                <td><?php echo $author['no_of_publications'] ?? 0; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-user-edit fa-5x text-primary mb-3"></i>
                                        <div class="d-grid gap-2">
                                            <a href="authors.php?action=edit&id=<?php echo $author['author_id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-edit"></i> Edit Author
                                            </a>
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="confirmDelete(<?php echo $author['author_id']; ?>, '<?php echo addslashes($author['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete Author
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Author's Books -->
                        <?php if (isset($author_books) && count($author_books) > 0): ?>
                        <div class="mt-4">
                            <h5>Books by this Author</h5>
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
                                        <?php foreach ($author_books as $book): ?>
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
                        <?php else: ?>
                        <div class="alert alert-info mt-4">
                            No books found for this author.
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
        function confirmDelete(authorId, authorName) {
            document.getElementById('deleteAuthorId').value = authorId;
            document.getElementById('deleteAuthorName').textContent = authorName;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>