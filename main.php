<?php
// Include database configuration
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get dashboard statistics
try {
    // Total books
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM books");
    $stmt->execute();
    $result = $stmt->fetch();
    $totalBooks = $result['total'];
    
    // Total members
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE user_type = 'member'");
    $stmt->execute();
    $result = $stmt->fetch();
    $totalMembers = $result['total'];
    
    // Books issued
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM circulation WHERE return_date IS NULL");
    $stmt->execute();
    $result = $stmt->fetch();
    $booksIssued = $result['total'];
    
    // Overdue books
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM circulation WHERE return_date IS NULL AND due_date < :today");
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    $result = $stmt->fetch();
    $overdue = $result['total'];
    
    // Recent activities
    $stmt = $conn->prepare("SELECT a.activity_date, a.activity_type, u.name as user_name, a.details 
                           FROM activities a 
                           LEFT JOIN users u ON a.user_id = u.id 
                           ORDER BY a.activity_date DESC LIMIT 10");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll();
    
} catch(PDOException $e) {
    // Log error
    error_log("Error getting dashboard data: " . $e->getMessage());
    // Set default values
    $totalBooks = 0;
    $totalMembers = 0;
    $booksIssued = 0;
    $overdue = 0;
    $recentActivities = [];
}

// Get books for books tab
try {
    $stmt = $conn->prepare("SELECT b.*, a.name as author_name, p.name as publisher_name,
                            CASE 
                                WHEN EXISTS (SELECT 1 FROM circulation c WHERE c.book_id = b.id AND c.return_date IS NULL) THEN 'Issued' 
                                WHEN EXISTS (SELECT 1 FROM book_requests br WHERE br.book_id = b.id AND br.status = 'pending') THEN 'Reserved' 
                                ELSE 'Available' 
                            END as status
                            FROM books b
                            LEFT JOIN authors a ON b.author_id = a.id
                            LEFT JOIN publishers p ON b.publisher_id = p.id
                            ORDER BY b.title
                            LIMIT 20");
    $stmt->execute();
    $books = $stmt->fetchAll();
    
    // Get authors
    $stmt = $conn->prepare("SELECT a.*, COUNT(b.id) as books_count 
                           FROM authors a
                           LEFT JOIN books b ON a.id = b.author_id
                           GROUP BY a.id
                           ORDER BY a.name
                           LIMIT 20");
    $stmt->execute();
    $authors = $stmt->fetchAll();
    
    // Get publishers
    $stmt = $conn->prepare("SELECT p.*, COUNT(b.id) as books_count 
                           FROM publishers p
                           LEFT JOIN books b ON p.id = b.publisher_id
                           GROUP BY p.id
                           ORDER BY p.name
                           LIMIT 20");
    $stmt->execute();
    $publishers = $stmt->fetchAll();
    
} catch(PDOException $e) {
    // Log error
    error_log("Error getting books data: " . $e->getMessage());
    // Set default values
    $books = [];
    $authors = [];
    $publishers = [];
}

// Get members for members tab
try {
    $stmt = $conn->prepare("SELECT u.*, d.name as department_name,
                            (SELECT COUNT(*) FROM circulation c WHERE c.user_id = u.id AND c.return_date IS NULL) as books_issued
                            FROM users u
                            LEFT JOIN departments d ON u.department_id = d.id
                            WHERE u.user_type = 'member'
                            ORDER BY u.name
                            LIMIT 20");
    $stmt->execute();
    $members = $stmt->fetchAll();
    
} catch(PDOException $e) {
    // Log error
    error_log("Error getting members data: " . $e->getMessage());
    // Set default values
    $members = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System - SRM University</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/library.js" defer></script>
    <script src="js/main.js" defer></script>
    <script src="js/circulation.js" defer></script>
    <script src="js/reports.js" defer></script>
    <style>
        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #1e3a8a;
            color: white;
            padding: 20px 0;
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.8;
        }

        .nav {
            background-color: #2563eb;
            border-radius: 5px;
            margin-bottom: 30px;
        }

        .nav ul {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
        }

        .nav ul li {
            padding: 15px 20px;
        }

        .nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav ul li a:hover {
            opacity: 0.8;
        }

        .main-content {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
        }

        .sidebar {
            flex: 1;
            min-width: 250px;
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .content {
            flex: 3;
            min-width: 300px;
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: #dbeafe;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 5px;
            color: #1e40af;
        }

        .stat-card p {
            color: #4b5563;
            font-size: 0.9rem;
        }

        h2 {
            margin-bottom: 20px;
            color: #1e3a8a;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        table th {
            background-color: #f3f4f6;
            font-weight: 600;
        }

        table tr:hover {
            background-color: #f9fafb;
        }

        .btn {
            background-color: #2563eb;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background-color: #1e40af;
        }

        .btn-green {
            background-color: #10b981;
        }

        .btn-green:hover {
            background-color: #059669;
        }

        .btn-red {
            background-color: #ef4444;
        }

        .btn-red:hover {
            background-color: #dc2626;
        }

        .search-bar {
            width: 100%;
            margin-bottom: 20px;
            display: flex;
        }

        .search-bar input {
            flex: 1;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px 0 0 4px;
            font-size: 1rem;
        }

        .search-bar button {
            padding: 10px 15px;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 1rem;
        }

        /* Tabs for module separation */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }

        .tab.active {
            border-bottom: 3px solid #2563eb;
            color: #2563eb;
            font-weight: 500;
        }

        /* Tab content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Library Management System</h1>
        <p>SRM University</p>
    </div>
    
    <div class="container">
        <div class="nav">
            <ul>
                <li><a href="#" onclick="showTab('dashboard')">Dashboard</a></li>
                <li><a href="#" onclick="showTab('books')">Books</a></li>
                <li><a href="#" onclick="showTab('members')">Members</a></li>
                <li><a href="#" onclick="showTab('circulation')">Circulation</a></li>
                <li><a href="#" onclick="showTab('requests')">Book Requests</a></li>
                <li><a href="#" onclick="showTab('reports')">Reports</a></li>
                <li><a href="#" onclick="showTab('settings')">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content active">
                <h2>Dashboard</h2>
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php echo $totalBooks; ?></h3>
                        <p>Total Books</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $totalMembers; ?></h3>
                        <p>Total Members</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $booksIssued; ?></h3>
                        <p>Books Issued</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $overdue; ?></h3>
                        <p>Overdue Books</p>
                    </div>
                </div>
                
                <h2>Recent Activities</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Activity</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivities as $activity): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($activity['activity_date'])); ?></td>
                            <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($activity['activity_type']); ?></td>
                            <td><?php echo htmlspecialchars($activity['details']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Books Tab -->
            <div id="books" class="tab-content">
                <h2>Books Management</h2>
                <div class="tabs">
                    <div class="tab active" onclick="showBookTab('books-list')">Books List</div>
                    <div class="tab" onclick="showBookTab('authors-list')">Authors</div>
                    <div class="tab" onclick="showBookTab('publishers-list')">Publishers</div>
                    <div class="tab" onclick="showBookTab('add-book')">Add New Book</div>
                </div>
                
                <!-- Books List Tab -->
                <div id="books-list" class="tab-content active">
                    <div class="search-bar">
                        <input type="text" id="book-search" placeholder="Search books..." onkeyup="searchBooks()">
                        <button onclick="searchBooks()">Search</button>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Publisher</th>
                                <th>ISBN</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author_name']); ?></td>
                                <td><?php echo htmlspecialchars($book['publisher_name']); ?></td>
                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                <td><?php echo htmlspecialchars($book['status']); ?></td>
                                <td>
                                    <a href="books.php?action=view&id=<?php echo $book['book_id']; ?>" class="btn">View</a>
                                    <a href="books.php?action=edit&id=<?php echo $book['book_id']; ?>" class="btn btn-green">Edit</a>
                                    <a href="books.php?action=delete&id=<?php echo $book['book_id']; ?>" class="btn btn-red" onclick="return confirm('Are you sure you want to delete this book?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Authors List Tab -->
                <div id="authors-list" class="tab-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Books Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($authors as $author): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($author['name']); ?></td>
                                <td><?php echo $author['books_count']; ?></td>
                                <td>
                                    <a href="authors.php?action=view&id=<?php echo $author['author_id']; ?>" class="btn">View</a>
                                    <a href="authors.php?action=edit&id=<?php echo $author['author_id']; ?>" class="btn btn-green">Edit</a>
                                    <a href="authors.php?action=delete&id=<?php echo $author['author_id']; ?>" class="btn btn-red" onclick="return confirm('Are you sure you want to delete this author?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Publishers List Tab -->
                <div id="publishers-list" class="tab-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Books Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($publishers as $publisher): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($publisher['name']); ?></td>
                                <td><?php echo $publisher['books_count']; ?></td>
                                <td>
                                    <a href="publishers.php?action=view&id=<?php echo $publisher['publisher_id']; ?>" class="btn">View</a>
                                    <a href="publishers.php?action=edit&id=<?php echo $publisher['publisher_id']; ?>" class="btn btn-green">Edit</a>
                                    <a href="publishers.php?action=delete&id=<?php echo $publisher['publisher_id']; ?>" class="btn btn-red" onclick="return confirm('Are you sure you want to delete this publisher?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Add Book Tab -->
                <div id="add-book" class="tab-content">
                    <form action="books.php" method="post">
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="author">Author</label>
                            <input type="text" id="author" name="author" required>
                        </div>
                        <div class="form-group">
                            <label for="publisher">Publisher</label>
                            <input type="text" id="publisher" name="publisher">
                        </div>
                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" id="isbn" name="isbn">
                        </div>
                        <div class="form-group">
                            <label for="publication_year">Publication Year</label>
                            <input type="number" id="publication_year" name="publication_year">
                        </div>
                        <div class="form-group">
                            <label for="genre">Genre</label>
                            <input type="text" id="genre" name="genre">
                        </div>
                        <div class="form-group">
                            <label for="copies">Number of Copies</label>
                            <input type="number" id="copies" name="copies" value="1" min="1">
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"></textarea>
                        </div>
                        <button type="submit" name="add_book" class="btn btn-green">Add Book</button>
                    </form>
                </div>
            </div>
            
            <!-- Members Tab -->
            <div id="members" class="tab-content">
                <h2>Members Management</h2>
                <div class="search-bar">
                    <input type="text" id="member-search" placeholder="Search members..." onkeyup="searchMembers()">
                    <button onclick="searchMembers()">Search</button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Books Issued</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['name']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td><?php echo htmlspecialchars($member['department_name']); ?></td>
                            <td><?php echo $member['books_issued']; ?></td>
                            <td>
                                <a href="users.php?action=view&id=<?php echo $member['user_id']; ?>" class="btn">View</a>
                                <a href="users.php?action=edit&id=<?php echo $member['user_id']; ?>" class="btn btn-green">Edit</a>
                                <a href="users.php?action=delete&id=<?php echo $member['user_id']; ?>" class="btn btn-red" onclick="return confirm('Are you sure you want to delete this member?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Circulation Tab -->
            <div id="circulation" class="tab-content">
                <h2>Circulation Management</h2>
                <div class="tabs">
                    <div class="tab active" onclick="showCirculationTab('issue-book')">Issue Book</div>
                    <div class="tab" onclick="showCirculationTab('return-book')">Return Book</div>
                    <div class="tab" onclick="showCirculationTab('circulation-history')">Circulation History</div>
                </div>
                
                <!-- Issue Book Tab -->
                <div id="issue-book" class="tab-content active">
                    <form action="circulation.php" method="post">
                        <div class="form-group">
                            <label for="book_id">Book</label>
                            <select id="book_id" name="book_id" required>
                                <option value="">Select Book</option>
                                <?php foreach ($books as $book): ?>
                                    <?php if ($book['status'] == 'Available'): ?>
                                    <option value="<?php echo $book['book_id']; ?>"><?php echo htmlspecialchars($book['title']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="user_id">Member</label>
                            <select id="user_id" name="user_id" required>
                                <option value="">Select Member</option>
                                <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['user_id']; ?>"><?php echo htmlspecialchars($member['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="due_date">Due Date</label>
                            <input type="date" id="due_date" name="due_date" required>
                        </div>
                        <button type="submit" name="issue_book" class="btn btn-green">Issue Book</button>
                    </form>
                </div>
                
                <!-- Return Book Tab -->
                <div id="return-book" class="tab-content">
                    <form action="circulation.php" method="post">
                        <div class="form-group">
                            <label for="lending_id">Select Book to Return</label>
                            <select id="lending_id" name="lending_id" required>
                                <option value="">Select Book</option>
                                <!-- This would be populated with currently issued books -->
                            </select>
                        </div>
                        <button type="submit" name="return_book" class="btn btn-green">Return Book</button>
                    </form>
                </div>
                
                <!-- Circulation History Tab -->
                <div id="circulation-history" class="tab-content">
                    <div class="search-bar">
                        <input type="text" id="circulation-search" placeholder="Search circulation..." onkeyup="searchCirculation()">
                        <button onclick="searchCirculation()">Search</button>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Member</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                                <th>Fine</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- This would be populated with circulation history -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Book Requests Tab -->
            <div id="requests" class="tab-content">
                <h2>Book Requests</h2>
                <div class="search-bar">
                    <input type="text" id="request-search" placeholder="Search requests..." onkeyup="searchRequests()">
                    <button onclick="searchRequests()">Search</button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Requested By</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- This would be populated with book requests -->
                    </tbody>
                </table>
            </div>
            
            <!-- Reports Tab -->
            <div id="reports" class="tab-content">
                <h2>Reports</h2>
                <div class="tabs">
                    <div class="tab active" onclick="showReportTab('books-report')">Books Report</div>
                    <div class="tab" onclick="showReportTab('members-report')">Members Report</div>
                    <div class="tab" onclick="showReportTab('circulation-report')">Circulation Report</div>
                    <div class="tab" onclick="showReportTab('fine-report')">Fine Report</div>
                </div>
                
                <!-- Books Report Tab -->
                <div id="books-report" class="tab-content active">
                    <!-- Books report content -->
                </div>
                
                <!-- Members Report Tab -->
                <div id="members-report" class="tab-content">
                    <!-- Members report content -->
                </div>
                
                <!-- Circulation Report Tab -->
                <div id="circulation-report" class="tab-content">
                    <!-- Circulation report content -->
                </div>
                
                <!-- Fine Report Tab -->
                <div id="fine-report" class="tab-content">
                    <!-- Fine report content -->
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div id="settings" class="tab-content">
                <h2>Settings</h2>
                <div class="tabs">
                    <div class="tab active" onclick="showSettingTab('general-settings')">General Settings</div>
                    <div class="tab" onclick="showSettingTab('user-settings')">User Settings</div>
                    <div class="tab" onclick="showSettingTab('system-settings')">System Settings</div>
                </div>
                
                <!-- General Settings Tab -->
                <div id="general-settings" class="tab-content active">
                    <form action="settings.php" method="post">
                        <div class="form-group">
                            <label for="library_name">Library Name</label>
                            <input type="text" id="library_name" name="library_name" value="SRM University Library">
                        </div>
                        <div class="form-group">
                            <label for="library_address">Library Address</label>
                            <textarea id="library_address" name="library_address">SRM University, Kattankulathur, Chennai - 603203</textarea>
                        </div>
                        <div class="form-group">
                            <label for="library_email">Library Email</label>
                            <input type="email" id="library_email" name="library_email" value="library@srm.edu.in">
                        </div>
                        <div class="form-group">
                            <label for="library_phone">Library Phone</label>
                            <input type="text" id="library_phone" name="library_phone" value="+91 1234567890">
                        </div>
                        <button type="submit" name="update_general_settings" class="btn btn-green">Save Changes</button>
                    </form>
                </div>
                
                <!-- User Settings Tab -->
                <div id="user-settings" class="tab-content">
                    <form action="settings.php" method="post">
                        <div class="form-group">
                            <label for="max_books_student">Maximum Books for Students</label>
                            <input type="number" id="max_books_student" name="max_books_student" value="3" min="1">
                        </div>
                        <div class="form-group">
                            <label for="max_books_faculty">Maximum Books for Faculty</label>
                            <input type="number" id="max_books_faculty" name="max_books_faculty" value="5" min="1">
                        </div>
                        <div class="form-group">
                            <label for="loan_period_student">Loan Period for Students (days)</label>
                            <input type="number" id="loan_period_student" name="loan_period_student" value="14" min="1">
                        </div>
                        <div class="form-group">
                            <label for="loan_period_faculty">Loan Period for Faculty (days)</label>
                            <input type="number" id="loan_period_faculty" name="loan_period_faculty" value="30" min="1">
                        </div>
                        <div class="form-group">
                            <label for="fine_rate">Fine Rate per Day (₹)</label>
                            <input type="number" id="fine_rate" name="fine_rate" value="5" min="0" step="0.5">
                        </div>
                        <button type="submit" name="update_user_settings" class="btn btn-green">Save Changes</button>
                    </form>
                </div>
                
                <!-- System Settings Tab -->
                <div id="system-settings" class="tab-content">
                    <form action="settings.php" method="post">
                        <div class="form-group">
                            <label for="backup_frequency">Database Backup Frequency</label>
                            <select id="backup_frequency" name="backup_frequency">
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="email_notifications">Email Notifications</label>
                            <select id="email_notifications" name="email_notifications">
                                <option value="enabled" selected>Enabled</option>
                                <option value="disabled">Disabled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="maintenance_mode">Maintenance Mode</label>
                            <select id="maintenance_mode" name="maintenance_mode">
                                <option value="enabled">Enabled</option>
                                <option value="disabled" selected>Disabled</option>
                            </select>
                        </div>
                        <button type="submit" name="update_system_settings" class="btn btn-green">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize the tabs
        document.addEventListener('DOMContentLoaded', function() {
            // Show dashboard by default
            showTab('dashboard');
        });
    </script>
</body>
</html>