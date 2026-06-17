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
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = $error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new book
    if (isset($_POST['add_book'])) {
        $title = sanitize_input($_POST['title']);
        $isbn = sanitize_input($_POST['isbn']);
        $publication_year = intval($_POST['publication_year']);
        $genre = sanitize_input($_POST['genre']);
        $subject = sanitize_input($_POST['subject']);
        $description = sanitize_input($_POST['description']);
        $price = floatval($_POST['price']);
        $keywords = sanitize_input($_POST['keywords']);
        $total_copies = intval($_POST['total_copies']);
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Insert book record
            $stmt = $conn->prepare("INSERT INTO books (title, isbn, publication_year, genre, subject, description, price, keywords, total_copies, available_copies) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $isbn, $publication_year, $genre, $subject, $description, $price, $keywords, $total_copies, $total_copies]);
            
            $new_book_id = $conn->lastInsertId();
            
            // Add authors
            if (isset($_POST['authors']) && is_array($_POST['authors'])) {
                $stmt = $conn->prepare("INSERT INTO book_authors (book_id, author_id) VALUES (?, ?)");
                foreach ($_POST['authors'] as $author_id) {
                    $stmt->execute([$new_book_id, $author_id]);
                }
            }
            
            // Add publisher
            if (isset($_POST['publisher_id']) && !empty($_POST['publisher_id'])) {
                $stmt = $conn->prepare("INSERT INTO book_publishers (book_id, publisher_id) VALUES (?, ?)");
                $stmt->execute([$new_book_id, $_POST['publisher_id']]);
            }
            
            // Add book copies
            if ($total_copies > 0) {
                $stmt = $conn->prepare("INSERT INTO book_copies (book_id, acquisition_date, `condition`, status, location) VALUES (?, CURDATE(), 'new', 'available', ?)");
                for ($i = 0; $i < $total_copies; $i++) {
                    $location = "Shelf " . chr(65 + rand(0, 25)) . "-" . rand(1, 100); // Random shelf location
                    $stmt->execute([$new_book_id, $location]);
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Book added successfully!";
            $action = 'list'; // Return to list view
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Update existing book
    if (isset($_POST['update_book'])) {
        $book_id = intval($_POST['book_id']);
        $title = sanitize_input($_POST['title']);
        $isbn = sanitize_input($_POST['isbn']);
        $publication_year = intval($_POST['publication_year']);
        $genre = sanitize_input($_POST['genre']);
        $subject = sanitize_input($_POST['subject']);
        $description = sanitize_input($_POST['description']);
        $price = floatval($_POST['price']);
        $keywords = sanitize_input($_POST['keywords']);
        $total_copies = intval($_POST['total_copies']);
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Get current available copies
            $stmt = $conn->prepare("SELECT total_copies, available_copies FROM books WHERE book_id = ?");
            $stmt->execute([$book_id]);
            $current_book = $stmt->fetch();
            
            // Calculate new available copies
            $current_total = $current_book['total_copies'];
            $current_available = $current_book['available_copies'];
            $borrowed = $current_total - $current_available;
            $new_available = max(0, $total_copies - $borrowed);
            
            // Update book record
            $stmt = $conn->prepare("UPDATE books SET title = ?, isbn = ?, publication_year = ?, genre = ?, 
                                   subject = ?, description = ?, price = ?, keywords = ?, 
                                   total_copies = ?, available_copies = ? WHERE book_id = ?");
            $stmt->execute([$title, $isbn, $publication_year, $genre, $subject, $description, $price, $keywords, $total_copies, $new_available, $book_id]);
            
            // Update authors - first remove existing
            $stmt = $conn->prepare("DELETE FROM book_authors WHERE book_id = ?");
            $stmt->execute([$book_id]);
            
            // Add new authors
            if (isset($_POST['authors']) && is_array($_POST['authors'])) {
                $stmt = $conn->prepare("INSERT INTO book_authors (book_id, author_id) VALUES (?, ?)");
                foreach ($_POST['authors'] as $author_id) {
                    $stmt->execute([$book_id, $author_id]);
                }
            }
            
            // Update publisher - first remove existing
            $stmt = $conn->prepare("DELETE FROM book_publishers WHERE book_id = ?");
            $stmt->execute([$book_id]);
            
            // Add new publisher
            if (isset($_POST['publisher_id']) && !empty($_POST['publisher_id'])) {
                $stmt = $conn->prepare("INSERT INTO book_publishers (book_id, publisher_id) VALUES (?, ?)");
                $stmt->execute([$book_id, $_POST['publisher_id']]);
            }
            
            // Handle book copies
            if ($total_copies > $current_total) {
                // Add more copies
                $new_copies = $total_copies - $current_total;
                $stmt = $conn->prepare("INSERT INTO book_copies (book_id, acquisition_date, `condition`, status, location) VALUES (?, CURDATE(), 'new', 'available', ?)");
                for ($i = 0; $i < $new_copies; $i++) {
                    $location = "Shelf " . chr(65 + rand(0, 25)) . "-" . rand(1, 100); // Random shelf location
                    $stmt->execute([$book_id, $location]);
                }
            } elseif ($total_copies < $current_total) {
                // Remove excess copies (only available ones)
                $copies_to_remove = $current_total - $total_copies;
                if ($copies_to_remove > 0) {
                    $stmt = $conn->prepare("DELETE FROM book_copies WHERE book_id = ? AND status = 'available' LIMIT ?");
                    $stmt->execute([$book_id, $copies_to_remove]);
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Book updated successfully!";
            $action = 'list'; // Return to list view
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Delete book
    if (isset($_POST['delete_book'])) {
        $book_id = intval($_POST['book_id']);
        
        try {
            // Check if any copies are borrowed
            $stmt = $conn->prepare("SELECT COUNT(*) as borrowed FROM book_copies bc 
                                   JOIN book_lending bl ON bc.copy_id = bl.copy_id 
                                   WHERE bc.book_id = ? AND bl.return_date IS NULL");
            $stmt->execute([$book_id]);
            $borrowed = $stmt->fetch()['borrowed'];
            
            if ($borrowed > 0) {
                $error_message = "Cannot delete book. There are $borrowed copies currently borrowed.";
            } else {
                // Begin transaction
                $conn->beginTransaction();
                
                // Delete book copies
                $stmt = $conn->prepare("DELETE FROM book_copies WHERE book_id = ?");
                $stmt->execute([$book_id]);
                
                // Delete book authors
                $stmt = $conn->prepare("DELETE FROM book_authors WHERE book_id = ?");
                $stmt->execute([$book_id]);
                
                // Delete book publishers
                $stmt = $conn->prepare("DELETE FROM book_publishers WHERE book_id = ?");
                $stmt->execute([$book_id]);
                
                // Delete book reviews
                $stmt = $conn->prepare("DELETE FROM book_reviews WHERE book_id = ?");
                $stmt->execute([$book_id]);
                
                // Delete book reservations
                $stmt = $conn->prepare("DELETE FROM book_reservations WHERE book_id = ?");
                $stmt->execute([$book_id]);
                
                // Finally delete the book
                $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
                $stmt->execute([$book_id]);
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Book deleted successfully!";
            }
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get book details for edit form
if ($action == 'edit' && $book_id > 0) {
    try {
        // Get book details
        $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();
        
        if (!$book) {
            $error_message = "Book not found!";
            $action = 'list';
        } else {
            // Get book authors
            $stmt = $conn->prepare("SELECT author_id FROM book_authors WHERE book_id = ?");
            $stmt->execute([$book_id]);
            $book_authors = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get book publisher
            $stmt = $conn->prepare("SELECT publisher_id FROM book_publishers WHERE book_id = ?");
            $stmt->execute([$book_id]);
            $publisher_id = $stmt->fetchColumn();
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get book details for view
if ($action == 'view' && $book_id > 0) {
    try {
        // Get book details with authors and publisher
        $stmt = $conn->prepare("SELECT b.*, GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') as author_names, 
                               p.name as publisher_name 
                               FROM books b 
                               LEFT JOIN book_authors ba ON b.book_id = ba.book_id 
                               LEFT JOIN authors a ON ba.author_id = a.author_id 
                               LEFT JOIN book_publishers bp ON b.book_id = bp.book_id 
                               LEFT JOIN publishers p ON bp.publisher_id = p.publisher_id 
                               WHERE b.book_id = ? 
                               GROUP BY b.book_id");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();
        
        if (!$book) {
            $error_message = "Book not found!";
            $action = 'list';
        } else {
            // Get book copies
            $stmt = $conn->prepare("SELECT * FROM book_copies WHERE book_id = ?");
            $stmt->execute([$book_id]);
            $book_copies = $stmt->fetchAll();
            
            // Get book reviews
            $stmt = $conn->prepare("SELECT br.*, u.name as user_name 
                                   FROM book_reviews br 
                                   JOIN users u ON br.user_id = u.user_id 
                                   WHERE br.book_id = ? 
                                   ORDER BY br.review_date DESC");
            $stmt->execute([$book_id]);
            $book_reviews = $stmt->fetchAll();
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get all books for list view
if ($action == 'list') {
    try {
        // Apply filters if any
        $where_clause = "";
        $params = [];
        
        if (isset($_GET['filter'])) {
            if ($_GET['filter'] == 'available') {
                $where_clause = " WHERE b.available_copies > 0";
            }
        }
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            if (empty($where_clause)) {
                $where_clause = " WHERE (b.title LIKE ? OR b.isbn LIKE ? OR b.genre LIKE ? OR b.subject LIKE ? OR b.keywords LIKE ? OR a.name LIKE ? OR p.name LIKE ?)"; 
            } else {
                $where_clause .= " AND (b.title LIKE ? OR b.isbn LIKE ? OR b.genre LIKE ? OR b.subject LIKE ? OR b.keywords LIKE ? OR a.name LIKE ? OR p.name LIKE ?)"; 
            }
            $params = array_merge($params, [$search, $search, $search, $search, $search, $search, $search]);
        }
        
        // Get books with authors and publisher
        $query = "SELECT b.*, GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') as author_names, 
                 p.name as publisher_name 
                 FROM books b 
                 LEFT JOIN book_authors ba ON b.book_id = ba.book_id 
                 LEFT JOIN authors a ON ba.author_id = a.author_id 
                 LEFT JOIN book_publishers bp ON b.book_id = bp.book_id 
                 LEFT JOIN publishers p ON bp.publisher_id = p.publisher_id 
                 $where_clause 
                 GROUP BY b.book_id 
                 ORDER BY b.title";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $books = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all authors for dropdown
try {
    $stmt = $conn->query("SELECT * FROM authors ORDER BY name");
    $authors = $stmt->fetchAll();
} catch(PDOException $e) {
    $error_message = "Error loading authors: " . $e->getMessage();
}

// Get all publishers for dropdown
try {
    $stmt = $conn->query("SELECT * FROM publishers ORDER BY name");
    $publishers = $stmt->fetchAll();
} catch(PDOException $e) {
    $error_message = "Error loading publishers: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books - Library Management System</title>
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
                            <a class="nav-link active" href="books.php">
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
                    <h1 class="h2">Books Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($action == 'list'): ?>
                        <a href="books.php?action=add" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add New Book
                        </a>
                        <?php else: ?>
                        <a href="books.php" class="btn btn-sm btn-secondary">
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
                <!-- Books List View -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <i class="fas fa-table me-1"></i>
                                Books List
                            </div>
                            <div class="col-md-6">
                                <form action="books.php" method="get" class="d-flex">
                                    <input type="hidden" name="action" value="list">
                                    <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search books..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
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
                                        <th>Title</th>
                                        <th>Author(s)</th>
                                        <th>Publisher</th>
                                        <th>ISBN</th>
                                        <th>Genre</th>
                                        <th>Copies</th>
                                        <th>Available</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($books) && count($books) > 0): ?>
                                        <?php foreach ($books as $book): ?>
                                        <tr>
                                            <td><?php echo $book['book_id']; ?></td>
                                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                                            <td><?php echo htmlspecialchars($book['author_names'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($book['publisher_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($book['genre'] ?? 'N/A'); ?></td>
                                            <td><?php echo $book['total_copies']; ?></td>
                                            <td>
                                                <?php if ($book['available_copies'] > 0): ?>
                                                    <span class="badge bg-success"><?php echo $book['available_copies']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="books.php?action=view&id=<?php echo $book['book_id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="books.php?action=edit&id=<?php echo $book['book_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" title="Delete" 
                                                        onclick="confirmDelete(<?php echo $book['book_id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No books found</td>
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
                                <p>Are you sure you want to delete the book: <span id="deleteBookTitle"></span>?</p>
                                <p class="text-danger">This action cannot be undone!</p>
                            </div>
                            <div class="modal-footer">
                                <form method="post" action="books.php">
                                    <input type="hidden" name="book_id" id="deleteBookId">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="delete_book" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'add'): ?>
                <!-- Add Book Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-plus me-1"></i>
                        Add New Book
                    </div>
                    <div class="card-body">
                        <form method="post" action="books.php" class="needs-validation" novalidate>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="subject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="subject" name="subject">
                                </div>
                                <div class="col-md-6">
                                    <label for="keywords" class="form-label">Keywords</label>
                                    <input type="text" class="form-control" id="keywords" name="keywords" placeholder="Separate keywords with commas">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="authors" class="form-label">Authors</label>
                                    <select class="form-control select2" id="authors" name="authors[]" multiple>
                                        <?php foreach ($authors as $author): ?>
                                        <option value="<?php echo $author['author_id']; ?>"><?php echo htmlspecialchars($author['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="publisher_id" class="form-label">Publisher</label>
                                    <select class="form-control select2" id="publisher_id" name="publisher_id">
                                        <option value="">Select Publisher</option>
                                        <?php foreach ($publishers as $publisher): ?>
                                        <option value="<?php echo $publisher['publisher_id']; ?>"><?php echo htmlspecialchars($publisher['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="total_copies" class="form-label">Number of Copies</label>
                                <input type="number" class="form-control" id="total_copies" name="total_copies" min="1" value="1" required>
                                <div class="invalid-feedback">Please enter at least 1 copy</div>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="books.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="add_book" class="btn btn-primary">Add Book</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'edit' && isset($book)): ?>
                <!-- Edit Book Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-edit me-1"></i>
                        Edit Book: <?php echo htmlspecialchars($book['title']); ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="books.php" class="needs-validation" novalidate>
                            <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>
                                    <div class="invalid-feedback">Please enter a title</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($book['isbn'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="publication_year" class="form-label">Publication Year</label>
                                    <input type="number" class="form-control" id="publication_year" name="publication_year" min="1000" max="<?php echo date('Y'); ?>" value="<?php echo $book['publication_year']; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="genre" class="form-label">Genre</label>
                                    <input type="text" class="form-control" id="genre" name="genre" value="<?php echo htmlspecialchars($book['genre'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="price" class="form-label">Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo $book['price']; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="subject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($book['subject'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="keywords" class="form-label">Keywords</label>
                                    <input type="text" class="form-control" id="keywords" name="keywords" placeholder="Separate keywords with commas" value="<?php echo htmlspecialchars($book['keywords'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($book['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="authors" class="form-label">Authors</label>
                                    <select class="form-control select2" id="authors" name="authors[]" multiple>
                                        <?php foreach ($authors as $author): ?>
                                        <option value="<?php echo $author['author_id']; ?>" <?php echo in_array($author['author_id'], $book_authors ?? []) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($author['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="publisher_id" class="form-label">Publisher</label>
                                    <select class="form-control select2" id="publisher_id" name="publisher_id">
                                        <option value="">Select Publisher</option>
                                        <?php foreach ($publishers as $publisher): ?>
                                        <option value="<?php echo $publisher['publisher_id']; ?>" <?php echo ($publisher_id ?? 0) == $publisher['publisher_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($publisher['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="total_copies" class="form-label">Number of Copies</label>
                                <input type="number" class="form-control" id="total_copies" name="total_copies" min="1" value="<?php echo $book['total_copies']; ?>" required>
                                <div class="invalid-feedback">Please enter at least 1 copy</div>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="books.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="update_book" class="btn btn-primary">Update Book</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'view' && isset($book)): ?>
                <!-- View Book Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-book me-1"></i>
                        Book Details: <?php echo htmlspecialchars($book['title']); ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                <p class="text-muted">
                                    By <?php echo htmlspecialchars($book['author_names'] ?? 'Unknown Author'); ?>
                                    <?php if (!empty($book['publisher_name'])): ?>
                                    | Published by <?php echo htmlspecialchars($book['publisher_name']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($book['publication_year'])): ?>
                                    | <?php echo $book['publication_year']; ?>
                                    <?php endif; ?>
                                </p>
                                
                                <div class="mb-3">
                                    <h5>Description</h5>
                                    <p><?php echo nl2br(htmlspecialchars($book['description'] ?? 'No description available.')); ?></p>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h5>Details</h5>
                                        <table class="table table-sm">
                                            <tr>
                                                <th>ISBN:</th>
                                                <td><?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Genre:</th>
                                                <td><?php echo htmlspecialchars($book['genre'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Subject:</th>
                                                <td><?php echo htmlspecialchars($book['subject'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Price:</th>
                                                <td>$<?php echo number_format($book['price'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Keywords:</th>
                                                <td><?php echo htmlspecialchars($book['keywords'] ?? 'N/A'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Availability</h5>
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Total Copies:</th>
                                                <td><?php echo $book['total_copies']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Available Copies:</th>
                                                <td>
                                                    <?php if ($book['available_copies'] > 0): ?>
                                                        <span class="badge bg-success"><?php echo $book['available_copies']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">0</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Added Date:</th>
                                                <td><?php echo date('Y-m-d', strtotime($book['added_date'])); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-book fa-5x text-primary mb-3"></i>
                                        <div class="d-grid gap-2">
                                            <a href="books.php?action=edit&id=<?php echo $book['book_id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-edit"></i> Edit Book
                                            </a>
                                            <a href="circulation.php?action=issue&book_id=<?php echo $book['book_id']; ?>" class="btn btn-success">
                                                <i class="fas fa-paper-plane"></i> Issue Book
                                            </a>
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="confirmDelete(<?php echo $book['book_id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                                <i class="fas fa-trash"></i> Delete Book
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Book Copies -->
                        <?php if (isset($book_copies) && count($book_copies) > 0): ?>
                        <div class="mt-4">
                            <h5>Book Copies</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Copy ID</th>
                                            <th>Acquisition Date</th>
                                            <th>Condition</th>
                                            <th>Status</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($book_copies as $copy): ?>
                                        <tr>
                                            <td><?php echo $copy['copy_id']; ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($copy['acquisition_date'])); ?></td>
                                            <td><?php echo ucfirst($copy['condition']); ?></td>
                                            <td>
                                                <?php if ($copy['status'] == 'available'): ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php elseif ($copy['status'] == 'borrowed'): ?>
                                                    <span class="badge bg-warning">Borrowed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($copy['status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($copy['location']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Book Reviews -->
                        <?php if (isset($book_reviews) && count($book_reviews) > 0): ?>
                        <div class="mt-4">
                            <h5>Book Reviews</h5>
                            <?php foreach ($book_reviews as $review): ?>
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($review['user_name']); ?></h6>
                                        <div>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $review['rating']): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star text-warning"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                    <small class="text-muted">Reviewed on <?php echo date('Y-m-d', strtotime($review['review_date'])); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Custom JS -->
    <script src="js/script.js"></script>
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2();
        });
        
        // Delete confirmation
        function confirmDelete(bookId, bookTitle) {
            document.getElementById('deleteBookId').value = bookId;
            document.getElementById('deleteBookTitle').textContent = bookTitle;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
