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
$periodical_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = $error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new periodical
    if (isset($_POST['add_periodical'])) {
        $title = sanitize_input($_POST['title']);
        $publisher_id = !empty($_POST['publisher_id']) ? intval($_POST['publisher_id']) : null;
        $frequency = sanitize_input($_POST['frequency']);
        $subject = sanitize_input($_POST['subject']);
        $price = floatval($_POST['price']);
        
        try {
            $stmt = $conn->prepare("INSERT INTO periodicals (title, publisher_id, frequency, subject, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $publisher_id, $frequency, $subject, $price]);
            
            $success_message = "Periodical added successfully!";
            $action = 'list'; // Return to list view
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Update existing periodical
    if (isset($_POST['update_periodical'])) {
        $periodical_id = intval($_POST['periodical_id']);
        $title = sanitize_input($_POST['title']);
        $publisher_id = !empty($_POST['publisher_id']) ? intval($_POST['publisher_id']) : null;
        $frequency = sanitize_input($_POST['frequency']);
        $subject = sanitize_input($_POST['subject']);
        $price = floatval($_POST['price']);
        
        try {
            $stmt = $conn->prepare("UPDATE periodicals SET title = ?, publisher_id = ?, frequency = ?, subject = ?, price = ? WHERE periodical_id = ?");
            $stmt->execute([$title, $publisher_id, $frequency, $subject, $price, $periodical_id]);
            
            $success_message = "Periodical updated successfully!";
            $action = 'list'; // Return to list view
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Delete periodical
    if (isset($_POST['delete_periodical'])) {
        $periodical_id = intval($_POST['periodical_id']);
        
        try {
            // Check if periodical has issues
            $stmt = $conn->prepare("SELECT COUNT(*) as issue_count FROM periodical_issues WHERE periodical_id = ?");
            $stmt->execute([$periodical_id]);
            $issue_count = $stmt->fetch()['issue_count'];
            
            if ($issue_count > 0) {
                $error_message = "Cannot delete periodical. There are $issue_count issues associated with this periodical.";
            } else {
                $stmt = $conn->prepare("DELETE FROM periodicals WHERE periodical_id = ?");
                $stmt->execute([$periodical_id]);
                
                $success_message = "Periodical deleted successfully!";
            }
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Add new issue
    if (isset($_POST['add_issue'])) {
        $periodical_id = intval($_POST['periodical_id']);
        $issue_number = sanitize_input($_POST['issue_number']);
        $publication_date = sanitize_input($_POST['publication_date']);
        $available = isset($_POST['available']) ? 1 : 0;
        
        try {
            $stmt = $conn->prepare("INSERT INTO periodical_issues (periodical_id, issue_number, publication_date, available) VALUES (?, ?, ?, ?)");
            $stmt->execute([$periodical_id, $issue_number, $publication_date, $available]);
            
            $success_message = "Issue added successfully!";
            $action = 'view'; // Return to view page
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    // Delete issue
    if (isset($_POST['delete_issue'])) {
        $issue_id = intval($_POST['issue_id']);
        $periodical_id = intval($_POST['periodical_id']);
        
        try {
            $stmt = $conn->prepare("DELETE FROM periodical_issues WHERE issue_id = ?");
            $stmt->execute([$issue_id]);
            
            $success_message = "Issue deleted successfully!";
            $action = 'view'; // Return to view page
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get periodical details for edit form
if ($action == 'edit' && $periodical_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM periodicals WHERE periodical_id = ?");
        $stmt->execute([$periodical_id]);
        $periodical = $stmt->fetch();
        
        if (!$periodical) {
            $error_message = "Periodical not found!";
            $action = 'list';
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get periodical details for view
if ($action == 'view' && $periodical_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT p.*, pub.name as publisher_name 
                               FROM periodicals p 
                               LEFT JOIN publishers pub ON p.publisher_id = pub.publisher_id 
                               WHERE p.periodical_id = ?");
        $stmt->execute([$periodical_id]);
        $periodical = $stmt->fetch();
        
        if (!$periodical) {
            $error_message = "Periodical not found!";
            $action = 'list';
        } else {
            // Get periodical issues
            $stmt = $conn->prepare("SELECT * FROM periodical_issues WHERE periodical_id = ? ORDER BY publication_date DESC");
            $stmt->execute([$periodical_id]);
            $periodical_issues = $stmt->fetchAll();
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
        $action = 'list';
    }
}

// Get all periodicals for list view
if ($action == 'list') {
    try {
        // Apply search filter if any
        $where_clause = "";
        $params = [];
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $where_clause = " WHERE p.title LIKE ? OR p.subject LIKE ? OR p.frequency LIKE ? OR pub.name LIKE ?";
            $params = [$search, $search, $search, $search];
        }
        
        $stmt = $conn->prepare("SELECT p.*, pub.name as publisher_name, 
                               (SELECT COUNT(*) FROM periodical_issues pi WHERE pi.periodical_id = p.periodical_id) as issue_count 
                               FROM periodicals p 
                               LEFT JOIN publishers pub ON p.publisher_id = pub.publisher_id 
                               $where_clause 
                               ORDER BY p.title");
        $stmt->execute($params);
        $periodicals = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
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
    <title>Periodicals - Library Management System</title>
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
                            <a class="nav-link active" href="periodicals.php">
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
                    <h1 class="h2">Periodicals Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($action == 'list'): ?>
                        <a href="periodicals.php?action=add" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add New Periodical
                        </a>
                        <?php else: ?>
                        <a href="periodicals.php" class="btn btn-sm btn-secondary">
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
                <!-- Periodicals List View -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <i class="fas fa-table me-1"></i>
                                Periodicals List
                            </div>
                            <div class="col-md-6">
                                <form action="periodicals.php" method="get" class="d-flex">
                                    <input type="hidden" name="action" value="list">
                                    <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search periodicals..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
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
                                        <th>Publisher</th>
                                        <th>Frequency</th>
                                        <th>Subject</th>
                                        <th>Price</th>
                                        <th>Issues</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($periodicals) && count($periodicals) > 0): ?>
                                        <?php foreach ($periodicals as $periodical): ?>
                                        <tr>
                                            <td><?php echo $periodical['periodical_id']; ?></td>
                                            <td><?php echo htmlspecialchars($periodical['title']); ?></td>
                                            <td><?php echo htmlspecialchars($periodical['publisher_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($periodical['frequency'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($periodical['subject'] ?? 'N/A'); ?></td>
                                            <td>$<?php echo number_format($periodical['price'], 2); ?></td>
                                            <td><?php echo $periodical['issue_count']; ?></td>
                                            <td>
                                                <a href="periodicals.php?action=view&id=<?php echo $periodical['periodical_id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="periodicals.php?action=edit&id=<?php echo $periodical['periodical_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" title="Delete" 
                                                        onclick="confirmDelete(<?php echo $periodical['periodical_id']; ?>, '<?php echo addslashes($periodical['title']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No periodicals found</td>
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
                                <p>Are you sure you want to delete the periodical: <span id="deletePeriodicalTitle"></span>?</p>
                                <p class="text-danger">This action cannot be undone!</p>
                            </div>
                            <div class="modal-footer">
                                <form method="post" action="periodicals.php">
                                    <input type="hidden" name="periodical_id" id="deletePeriodicalId">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="delete_periodical" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'add'): ?>
                <!-- Add Periodical Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-plus me-1"></i>
                        Add New Periodical
                    </div>
                    <div class="card-body">
                        <form method="post" action="periodicals.php" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                                <div class="invalid-feedback">Please enter a title</div>
                            </div>
                            <div class="mb-3">
                                <label for="publisher_id" class="form-label">Publisher</label>
                                <select class="form-control select2" id="publisher_id" name="publisher_id">
                                    <option value="">Select Publisher</option>
                                    <?php foreach ($publishers as $publisher): ?>
                                    <option value="<?php echo $publisher['publisher_id']; ?>"><?php echo htmlspecialchars($publisher['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="frequency" class="form-label">Frequency</label>
                                <select class="form-control" id="frequency" name="frequency">
                                    <option value="">Select Frequency</option>
                                    <option value="Daily">Daily</option>
                                    <option value="Weekly">Weekly</option>
                                    <option value="Bi-weekly">Bi-weekly</option>
                                    <option value="Monthly">Monthly</option>
                                    <option value="Bi-monthly">Bi-monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Semi-annually">Semi-annually</option>
                                    <option value="Annually">Annually</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject">
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="0.00">
                                </div>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="periodicals.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="add_periodical" class="btn btn-primary">Add Periodical</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'edit' && isset($periodical)): ?>
                <!-- Edit Periodical Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-edit me-1"></i>
                        Edit Periodical: <?php echo htmlspecialchars($periodical['title']); ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="periodicals.php" class="needs-validation" novalidate>
                            <input type="hidden" name="periodical_id" value="<?php echo $periodical['periodical_id']; ?>">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($periodical['title']); ?>" required>
                                <div class="invalid-feedback">Please enter a title</div>
                            </div>
                            <div class="mb-3">
                                <label for="publisher_id" class="form-label">Publisher</label>
                                <select class="form-control select2" id="publisher_id" name="publisher_id">
                                    <option value="">Select Publisher</option>
                                    <?php foreach ($publishers as $publisher): ?>
                                    <option value="<?php echo $publisher['publisher_id']; ?>" <?php echo ($periodical['publisher_id'] == $publisher['publisher_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($publisher['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="frequency" class="form-label">Frequency</label>
                                <select class="form-control" id="frequency" name="frequency">
                                    <option value="">Select Frequency</option>
                                    <option value="Daily" <?php echo ($periodical['frequency'] == 'Daily') ? 'selected' : ''; ?>>Daily</option>
                                    <option value="Weekly" <?php echo ($periodical['frequency'] == 'Weekly') ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="Bi-weekly" <?php echo ($periodical['frequency'] == 'Bi-weekly') ? 'selected' : ''; ?>>Bi-weekly</option>
                                    <option value="Monthly" <?php echo ($periodical['frequency'] == 'Monthly') ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="Bi-monthly" <?php echo ($periodical['frequency'] == 'Bi-monthly') ? 'selected' : ''; ?>>Bi-monthly</option>
                                    <option value="Quarterly" <?php echo ($periodical['frequency'] == 'Quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                                    <option value="Semi-annually" <?php echo ($periodical['frequency'] == 'Semi-annually') ? 'selected' : ''; ?>>Semi-annually</option>
                                    <option value="Annually" <?php echo ($periodical['frequency'] == 'Annually') ? 'selected' : ''; ?>>Annually</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($periodical['subject'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo $periodical['price']; ?>">
                                </div>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="periodicals.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="update_periodical" class="btn btn-primary">Update Periodical</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'view' && isset($periodical)): ?>
                <!-- View Periodical Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-newspaper me-1"></i>
                        Periodical Details: <?php echo htmlspecialchars($periodical['title']); ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h4><?php echo htmlspecialchars($periodical['title']); ?></h4>
                                <p class="text-muted">
                                    <?php if (!empty($periodical['publisher_name'])): ?>
                                    Published by <?php echo htmlspecialchars($periodical['publisher_name']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($periodical['frequency'])): ?>
                                    | <?php echo htmlspecialchars($periodical['frequency']); ?>
                                    <?php endif; ?>
                                </p>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h5>Details</h5>
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Subject:</th>
                                                <td><?php echo htmlspecialchars($periodical['subject'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Price:</th>
                                                <td>$<?php echo number_format($periodical['price'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Issues:</th>
                                                <td><?php echo count($periodical_issues ?? []); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-newspaper fa-5x text-primary mb-3"></i>
                                        <div class="d-grid gap-2">
                                            <a href="periodicals.php?action=edit&id=<?php echo $periodical['periodical_id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-edit"></i> Edit Periodical
                                            </a>
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="confirmDelete(<?php echo $periodical['periodical_id']; ?>, '<?php echo addslashes($periodical['title']); ?>')">
                                                <i class="fas fa-trash"></i> Delete Periodical
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Periodical Issues -->
                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Issues</h5>
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addIssueModal">
                                    <i class="fas fa-plus"></i> Add New Issue
                                </button>
                            </div>
                            
                            <?php if (isset($periodical_issues) && count($periodical_issues) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Issue Number</th>
                                            <th>Publication Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($periodical_issues as $issue): ?>
                                        <tr>
                                            <td><?php echo $issue['issue_id']; ?></td>
                                            <td><?php echo htmlspecialchars($issue['issue_number']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($issue['publication_date'])); ?></td>
                                            <td>
                                                <?php if ($issue['available']): ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Not Available</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="confirmDeleteIssue(<?php echo $issue['issue_id']; ?>, '<?php echo addslashes($issue['issue_number']); ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                No issues found for this periodical.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Add Issue Modal -->
                <div class="modal fade" id="addIssueModal" tabindex="-1" aria-labelledby="addIssueModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="addIssueModalLabel">Add New Issue</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" action="periodicals.php?action=view&id=<?php echo $periodical['periodical_id']; ?>">
                                <div class="modal-body">
                                    <input type="hidden" name="periodical_id" value="<?php echo $periodical['periodical_id']; ?>">
                                    <div class="mb-3">
                                        <label for="issue_number" class="form-label">Issue Number</label>
                                        <input type="text" class="form-control" id="issue_number" name="issue_number" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="publication_date" class="form-label">Publication Date</label>
                                        <input type="date" class="form-control" id="publication_date" name="publication_date" required>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="available" name="available" checked>
                                        <label class="form-check-label" for="available">Available</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_issue" class="btn btn-success">Add Issue</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Issue Confirmation Modal -->
                <div class="modal fade" id="deleteIssueModal" tabindex="-1" aria-labelledby="deleteIssueModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteIssueModalLabel">Confirm Delete Issue</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete the issue: <span id="deleteIssueNumber"></span>?</p>
                                <p class="text-danger">This action cannot be undone!</p>
                            </div>
                            <div class="modal-footer">
                                <form method="post" action="periodicals.php?action=view&id=<?php echo $periodical['periodical_id']; ?>">
                                    <input type="hidden" name="issue_id" id="deleteIssueId">
                                    <input type="hidden" name="periodical_id" value="<?php echo $periodical['periodical_id']; ?>">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="delete_issue" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
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
        });
        
        // Delete confirmation for periodical
        function confirmDelete(periodicalId, periodicalTitle) {
            document.getElementById('deletePeriodicalId').value = periodicalId;
            document.getElementById('deletePeriodicalTitle').textContent = periodicalTitle;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        // Delete confirmation for issue
        function confirmDeleteIssue(issueId, issueNumber) {
            document.getElementById('deleteIssueId').value = issueId;
            document.getElementById('deleteIssueNumber').textContent = issueNumber;
            var deleteIssueModal = new bootstrap.Modal(document.getElementById('deleteIssueModal'));
            deleteIssueModal.show();
        }
    </script>
</body>
</html>