<?php
// Include database configuration
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Book ID is required']);
    exit;
}

// Sanitize input
$bookId = sanitize_input($_GET['id']);

// Get book details
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
                            WHERE b.id = :book_id");
    $stmt->bindParam(':book_id', $bookId);
    $stmt->execute();
    $book = $stmt->fetch();
    
    if (!$book) {
        // Book not found
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Book not found']);
        exit;
    }
    
    // Format response
    $response = [
        'id' => $book['id'],
        'title' => $book['title'],
        'author' => $book['author_name'],
        'genre' => $book['genre'],
        'publisher' => $book['publisher_name'],
        'isbn' => $book['isbn'],
        'publication_year' => $book['publication_year'],
        'copies' => $book['copies'],
        'available_copies' => $book['available_copies'],
        'status' => $book['status'],
        'description' => $book['description']
    ];
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch(PDOException $e) {
    // Log error and return error response
    error_log("Error getting book details: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error occurred']);
    exit;
}