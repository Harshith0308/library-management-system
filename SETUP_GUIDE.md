# Library Management System - Setup Guide

This guide will walk you through the process of setting up the Library Management System using XAMPP and phpMyAdmin.

## Step 1: Install and Start XAMPP

1. If you haven't already installed XAMPP, download it from [https://www.apachefriends.org/](https://www.apachefriends.org/) and install it.
2. Open the XAMPP Control Panel.
3. Start the Apache and MySQL services by clicking the "Start" buttons next to them.
4. Ensure both services show a green background, indicating they are running properly.

## Step 2: Create the Database

### Option 1: Using phpMyAdmin (Recommended)

1. Open your web browser and navigate to: [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)
2. Click on the "SQL" tab at the top of the page.
3. Open the file `library_schema.sql` from the project folder.
4. Copy and paste the entire content into the SQL query window in phpMyAdmin.
5. Click "Go" to execute the SQL statements.
6. The database `library_management_system` will be created with all required tables.

### Option 2: Using the Setup Script

1. Navigate to [http://localhost/library/DBMS_Project/setup_admin.php](http://localhost/library/DBMS_Project/setup_admin.php)
2. This script will:
   - Check if the database exists and create it if needed
   - Create an admin user if one doesn't exist
   - Provide login credentials for the system

## Step 3: Verify Database Setup

After creating the database, verify that all tables were created correctly:

1. In phpMyAdmin, click on the `library_management_system` database in the left sidebar.
2. You should see the following tables:
   - authors
   - book_authors
   - book_copies
   - book_lending
   - book_publishers
   - book_requests
   - book_reservations
   - book_reviews
   - books
   - library_statistics
   - periodical_issues
   - periodicals
   - publishers
   - staff
   - users

## Step 4: Access the System

1. Open your web browser and navigate to: [http://localhost/library/DBMS_Project/login.php](http://localhost/library/DBMS_Project/login.php)
2. Log in with the admin credentials:
   - Email: admin@library.com
   - Password: admin123

## Step 5: Add Sample Data

To make the system more usable, add some sample data:

1. Add Authors: Navigate to the Authors section and add several authors.
2. Add Publishers: Navigate to the Publishers section and add several publishers.
3. Add Books: Navigate to the Books section and add books, associating them with authors and publishers.
4. Add Users: Create some regular user accounts for testing circulation features.

## Troubleshooting

### Database Connection Issues

If you encounter database connection issues:

1. Ensure MySQL service is running in XAMPP Control Panel.
2. Verify database name, username, and password in `config.php` are correct.
3. Check if the database was created successfully in phpMyAdmin.
4. Try restarting the Apache and MySQL services.

### Login Issues

If you cannot log in:

1. Ensure you created the admin user correctly using the setup_admin.php script.
2. Verify the email and password are correct.
3. Check for any error messages on the login page.
4. If needed, manually create a user in the `users` table with a properly hashed password.

### PHP Errors

If you see PHP errors:

1. Check that XAMPP is properly installed and configured.
2. Ensure PHP version is compatible (PHP 7.0 or higher recommended).
3. Check file permissions on the project folders.

## Database Schema

The library management system uses the following main tables:

1. `books` - Stores book information (title, ISBN, etc.)
2. `authors` - Stores author information
3. `publishers` - Stores publisher information
4. `users` - Stores user/member information
5. `book_lending` - Tracks book circulation (borrowing/returning)
6. `book_copies` - Tracks individual book copies
7. `book_requests` - Manages book acquisition requests
8. `periodicals` - Manages periodicals/journals
9. `staff` - Stores staff information

## System Features

Once set up, the Library Management System provides the following features:

1. Book management (add, edit, delete books)
2. Author and publisher management
3. User management (students, faculty, staff, admins)
4. Book circulation (issue, return, renew)
5. Book requests and reservations
6. Periodicals management
7. Reports generation
8. Fine calculation for overdue books

## Need Help?

If you encounter any issues during setup, please check the following:

1. XAMPP services are running properly
2. Database connection settings in config.php are correct
3. All required tables are created in the database
4. Admin user is created correctly

Enjoy using your Library Management System!