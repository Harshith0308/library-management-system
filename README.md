# Library Management System

A full-stack Library Management System built with **PHP** and **MySQL**. It provides a complete back-office for a library — managing books, authors, publishers, members, staff, periodicals and the full book circulation workflow (issue, return, renew) with automatic fine calculation for overdue items.

This was built as my Semester 4 DBMS project.

## Features

- **Authentication & roles** — secure login with hashed passwords and admin/staff/member access levels
- **Book management** — add, edit, delete and search books, with individual copy tracking
- **Authors & publishers** — full CRUD and association with books
- **Member management** — students, faculty, staff and admin accounts
- **Circulation** — issue, return and renew books; automatic overdue fine calculation
- **Book requests & reservations** — members can request acquisitions and reserve titles
- **Periodicals** — manage journals/magazines and their issues
- **Reports & dashboard** — circulation stats, overdue lists and library statistics

## Tech Stack

- **Backend:** PHP (PDO for database access)
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Server:** Apache (via XAMPP)

## Project Structure

```
DBMS_Project/
├── index.php             # Dashboard / landing page
├── login.php             # Authentication
├── logout.php
├── config.php            # Database connection & helper functions
├── books.php             # Book management
├── authors.php           # Author management
├── publishers.php        # Publisher management
├── users.php             # Member/user management
├── staff.php             # Staff management
├── circulation.php       # Issue / return / renew
├── book_requests.php     # Acquisition requests
├── periodicals.php       # Periodicals & issues
├── reports.php           # Reports & statistics
├── settings.php
├── setup_admin.php       # First-run admin/database setup helper
├── get_*.php             # AJAX endpoints (dashboard stats, book lookup, etc.)
├── library_schema.sql    # Full database schema
├── css/                  # Stylesheets
├── js/                   # Client-side scripts
└── includes/             # Shared header & sidebar
```

## Getting Started

> Full step-by-step instructions are in [SETUP_GUIDE.md](SETUP_GUIDE.md).

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Place this project inside your `htdocs` directory.
3. Import `library_schema.sql` through phpMyAdmin to create the `library_management_system` database.
4. Adjust the credentials in `config.php` if your MySQL setup differs from the XAMPP defaults.
5. Visit `setup_admin.php` once to create the database (if needed) and the initial admin user.
6. Open `login.php` in your browser and sign in.

**Default admin credentials**

| Email | Password |
| --- | --- |
| admin@library.com | admin123 |

> Change the default password after your first login.

## Database

The schema (`library_schema.sql`) defines the core tables: `books`, `authors`, `publishers`, `users`, `staff`, `book_copies`, `book_lending`, `book_requests`, `book_reservations`, `periodicals` and supporting tables for reviews and statistics.

## License

Released under the [MIT License](LICENSE).
