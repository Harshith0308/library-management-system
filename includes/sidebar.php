<?php
// This file contains the sidebar navigation for all pages
// It includes links to all main sections of the application

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if user is admin
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
?>

<!-- Sidebar -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <i class="fas fa-book-reader fa-3x text-white"></i>
            <h5 class="text-white mt-2">Library System</h5>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'books.php') ? 'active' : ''; ?>" href="books.php">
                    <i class="fas fa-book"></i> Books
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'authors.php') ? 'active' : ''; ?>" href="authors.php">
                    <i class="fas fa-user-edit"></i> Authors
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'publishers.php') ? 'active' : ''; ?>" href="publishers.php">
                    <i class="fas fa-building"></i> Publishers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'periodicals.php') ? 'active' : ''; ?>" href="periodicals.php">
                    <i class="fas fa-newspaper"></i> Periodicals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'circulation.php') ? 'active' : ''; ?>" href="circulation.php">
                    <i class="fas fa-exchange-alt"></i> Circulation
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <?php if ($is_admin): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'staff.php') ? 'active' : ''; ?>" href="staff.php">
                    <i class="fas fa-user-tie"></i> Staff
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'book_requests.php') ? 'active' : ''; ?>" href="book_requests.php">
                    <i class="fas fa-clipboard-list"></i> Book Requests
                </a>
            </li>
            <?php if ($is_admin): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item mt-5">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</nav>