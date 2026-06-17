<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Set header to return JSON
header('Content-Type: application/json');

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'get_circulation_data';

// Handle different actions
try {
    switch ($action) {
        case 'get_circulation_data':
            // Get circulation data
            $stmt = $conn->prepare("SELECT bl.lending_id, b.title as book_title, u.name as member_name, 
                                  bl.issue_date, bl.due_date, bl.return_date, 
                                  CASE 
                                      WHEN bl.return_date IS NOT NULL THEN 'Returned' 
                                      WHEN bl.due_date < CURRENT_DATE THEN 'Overdue' 
                                      ELSE 'Issued' 
                                  END as status,
                                  bl.fine_amount
                                  FROM book_lending bl
                                  JOIN book_copies bc ON bl.copy_id = bc.copy_id
                                  JOIN books b ON bc.book_id = b.book_id
                                  JOIN users u ON bl.user_id = u.user_id
                                  ORDER BY bl.issue_date DESC
                                  LIMIT 50");
            $stmt->execute();
            $circulation = $stmt->fetchAll();
            
            // Format dates for display
            foreach ($circulation as &$item) {
                $item['issue_date'] = date('M d, Y', strtotime($item['issue_date']));
                $item['due_date'] = date('M d, Y', strtotime($item['due_date']));
                if ($item['return_date']) {
                    $item['return_date'] = date('M d, Y', strtotime($item['return_date']));
                }
            }
            
            echo json_encode($circulation);
            break;
            
        case 'get_issued_books':
            // Get currently issued books for return book dropdown
            $stmt = $conn->prepare("SELECT bl.lending_id, b.title as book_title, u.name as member_name
                                  FROM book_lending bl
                                  JOIN book_copies bc ON bl.copy_id = bc.copy_id
                                  JOIN books b ON bc.book_id = b.book_id
                                  JOIN users u ON bl.user_id = u.user_id
                                  WHERE bl.return_date IS NULL
                                  ORDER BY bl.issue_date DESC");
            $stmt->execute();
            $issuedBooks = $stmt->fetchAll();
            
            echo json_encode($issuedBooks);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}