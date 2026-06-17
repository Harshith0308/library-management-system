-- Create the database
CREATE DATABASE IF NOT EXISTS library_management_system;
USE library_management_system;

-- Create a table for books
CREATE TABLE IF NOT EXISTS books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    isbn VARCHAR(20) UNIQUE,
    publication_year INT,
    genre VARCHAR(50),
    subject VARCHAR(100),
    description TEXT,
    price DECIMAL(10, 2),
    keywords TEXT,
    total_copies INT DEFAULT 0,
    available_copies INT DEFAULT 0,
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create a table for authors
CREATE TABLE IF NOT EXISTS authors (
    author_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    biography TEXT,
    contact_info VARCHAR(255),
    no_of_publications INT DEFAULT 0
);

-- Create a book-author relationship table (many-to-many)
CREATE TABLE IF NOT EXISTS book_authors (
    book_id INT,
    author_id INT,
    PRIMARY KEY (book_id, author_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES authors(author_id) ON DELETE CASCADE
);

-- Create a table for publishers
CREATE TABLE IF NOT EXISTS publishers (
    publisher_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    contact_info VARCHAR(255),
    no_of_publications INT DEFAULT 0
);

-- Create a book-publisher relationship table
CREATE TABLE IF NOT EXISTS book_publishers (
    book_id INT,
    publisher_id INT,
    PRIMARY KEY (book_id, publisher_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (publisher_id) REFERENCES publishers(publisher_id) ON DELETE CASCADE
);

-- Create a table for periodicals
CREATE TABLE IF NOT EXISTS periodicals (
    periodical_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    publisher_id INT,
    frequency VARCHAR(50), -- weekly, monthly, quarterly, etc.
    subject VARCHAR(100),
    price DECIMAL(10, 2),
    FOREIGN KEY (publisher_id) REFERENCES publishers(publisher_id) ON DELETE SET NULL
);

-- Create a table for periodical issues
CREATE TABLE IF NOT EXISTS periodical_issues (
    issue_id INT AUTO_INCREMENT PRIMARY KEY,
    periodical_id INT,
    issue_number VARCHAR(20),
    publication_date DATE,
    available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (periodical_id) REFERENCES periodicals(periodical_id) ON DELETE CASCADE
);

-- Create a table for book inventory (individual copies)
CREATE TABLE IF NOT EXISTS book_copies (
    copy_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    acquisition_date DATE,
    `condition` VARCHAR(50), -- new, good, fair, poor
    status VARCHAR(20) DEFAULT 'available', -- available, borrowed, reserved, lost, damaged
    location VARCHAR(100), -- shelf location
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
);

-- Create a table for users/members
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    user_type ENUM('student', 'faculty', 'staff', 'admin') NOT NULL,
    department VARCHAR(50),
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
);

-- Create a table for staff
CREATE TABLE IF NOT EXISTS staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    role VARCHAR(50) NOT NULL, -- librarian, assistant, admin, etc.
    hire_date DATE,
    salary DECIMAL(10, 2),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create a table for book lending/circulation
CREATE TABLE IF NOT EXISTS book_lending (
    lending_id INT AUTO_INCREMENT PRIMARY KEY,
    copy_id INT,
    user_id INT,
    issue_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    return_date TIMESTAMP NULL,
    renewed_times INT DEFAULT 0,
    fine_amount DECIMAL(10, 2) DEFAULT 0,
    fine_paid BOOLEAN DEFAULT FALSE,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    FOREIGN KEY (copy_id) REFERENCES book_copies(copy_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create a table for book requests
CREATE TABLE IF NOT EXISTS book_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(100) NOT NULL,
    author VARCHAR(100),
    publisher VARCHAR(100),
    isbn VARCHAR(20),
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected', 'acquired') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Create a table for book reservations
CREATE TABLE IF NOT EXISTS book_reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    user_id INT,
    reservation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    status ENUM('active', 'fulfilled', 'expired', 'cancelled') DEFAULT 'active',
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create a table for library usage statistics
CREATE TABLE IF NOT EXISTS library_statistics (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE,
    books_borrowed INT DEFAULT 0,
    books_returned INT DEFAULT 0,
    new_members INT DEFAULT 0,
    active_users INT DEFAULT 0,
    fines_collected DECIMAL(10, 2) DEFAULT 0
);

-- Create a table for book reviews
CREATE TABLE IF NOT EXISTS book_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    user_id INT,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT,
    review_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);