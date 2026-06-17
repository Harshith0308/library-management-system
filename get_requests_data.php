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
$action = isset($_GET['action']) ? $_GET['action'] : 'get_requests_data';

// Handle different actions
try {
    switch ($action) {
        case 'get_requests_data':
            // Get book requests data
            $stmt = $conn->prepare("SELECT br.request_id, br.title, u.name as requested_by, 
                                  br.request_date, br.status, br.notes
                                  FROM book_requests br
                                  JOIN users u ON br.user_id = u.user_id
                                  ORDER BY br.request_date DESC
                                  LIMIT 50");
            $stmt->execute();
            $requests = $stmt->fetchAll();
            
            // Format dates for display
            foreach ($requests as &$request) {
                $request['request_date'] = date('M d, Y', strtotime($request['request_date']));
                // Capitalize status
                $request['status'] = ucfirst($request['status']);
            }
            
            echo json_encode($requests);
            break;
            
        case 'get_request_details':
            // Get details for a specific request
            $request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if ($request_id <= 0) {
                echo json_encode(['error' => 'Invalid request ID']);
                break;
            }
            
            $stmt = $conn->prepare("SELECT br.*, u.name as requested_by, u.email
                                  FROM book_requests br
                                  JOIN users u ON br.user_id = u.user_id
                                  WHERE br.request_id = ?");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request) {
                echo json_encode(['error' => 'Request not found']);
                break;
            }
            
            // Format date
            $request['request_date'] = date('M d, Y', strtotime($request['request_date']));
            
            echo json_encode($request);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}