<?php
// Include database configuration
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize response array
$response = [
    'totalBooks' => 0,
    'totalMembers' => 0,
    'booksIssued' => 0,
    'overdue' => 0,
    'recentActivities' => []
];

// Get total books count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM books");
    $stmt->execute();
    $result = $stmt->fetch();
    $response['totalBooks'] = $result['total'];
} catch(PDOException $e) {
    // Log error but continue
    error_log("Error getting total books: " . $e->getMessage());
}

// Get total members count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE user_type = 'member'");
    $stmt->execute();
    $result = $stmt->fetch();
    $response['totalMembers'] = $result['total'];
} catch(PDOException $e) {
    error_log("Error getting total members: " . $e->getMessage());
}

// Get books issued count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM circulation WHERE return_date IS NULL");
    $stmt->execute();
    $result = $stmt->fetch();
    $response['booksIssued'] = $result['total'];
} catch(PDOException $e) {
    error_log("Error getting issued books: " . $e->getMessage());
}

// Get overdue books count
try {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM circulation WHERE return_date IS NULL AND due_date < :today");
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    $result = $stmt->fetch();
    $response['overdue'] = $result['total'];
} catch(PDOException $e) {
    error_log("Error getting overdue books: " . $e->getMessage());
}

// Get recent activities
try {
    $stmt = $conn->prepare("SELECT a.activity_date, a.activity_type, u.name as user_name, a.details 
                           FROM activities a 
                           LEFT JOIN users u ON a.user_id = u.id 
                           ORDER BY a.activity_date DESC LIMIT 10");
    $stmt->execute();
    $activities = $stmt->fetchAll();
    
    foreach($activities as $activity) {
        $response['recentActivities'][] = [
            'date' => date('Y-m-d', strtotime($activity['activity_date'])),
            'activity' => $activity['activity_type'],
            'user' => $activity['user_name'],
            'details' => $activity['details']
        ];
    }
} catch(PDOException $e) {
    error_log("Error getting recent activities: " . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);