// Library Management System JavaScript File

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tab functionality
    initializeTabs();
    
    // Initialize modal functionality
    initializeModals();
    
    // Initialize search functionality
    initializeSearch();
    
    // Load initial data
    loadDashboardData();
});

// Tab functionality
function initializeTabs() {
    // Main navigation tabs
    document.querySelectorAll('.nav ul li a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('onclick').match(/showTab\('(.*?)'\)/)[1];
            showTab(tabId);
        });
    });
    
    // Book sub-tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            if (this.getAttribute('onclick')) {
                const tabFunction = this.getAttribute('onclick');
                if (tabFunction.includes('showBookTab')) {
                    const tabId = tabFunction.match(/showBookTab\('(.*?)'\)/)[1];
                    showBookTab(tabId);
                }
            }
        });
    });
}

// Show main tab content
function showTab(tabId) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show the selected tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Update active state in navigation
    document.querySelectorAll('.nav ul li a').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('onclick') && link.getAttribute('onclick').includes(tabId)) {
            link.classList.add('active');
        }
    });
}

// Show book sub-tab content
function showBookTab(tabId) {
    // Hide all book tab contents
    document.querySelectorAll('#books .tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show the selected book tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Update active state in book tabs
    document.querySelectorAll('#books .tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.getAttribute('onclick') && tab.getAttribute('onclick').includes(tabId)) {
            tab.classList.add('active');
        }
    });
}

// Modal functionality
function initializeModals() {
    // Open modal
    document.querySelectorAll('button[onclick^="openModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.getAttribute('onclick').match(/openModal\('(.*?)'\)/)[1];
            openModal(modalId);
        });
    });
    
    // Close modal when clicking the close button
    document.querySelectorAll('.close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Close modal when clicking outside the modal content
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
}

// Open modal function
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

// Search functionality
function initializeSearch() {
    // Book search
    const bookSearchBtn = document.querySelector('#books .search-bar button');
    if (bookSearchBtn) {
        bookSearchBtn.addEventListener('click', searchBooks);
    }
    
    // Member search
    const memberSearchBtn = document.querySelector('#members .search-bar button');
    if (memberSearchBtn) {
        memberSearchBtn.addEventListener('click', searchMembers);
    }
}

// Search books function
function searchBooks() {
    const searchTerm = document.getElementById('bookSearch').value.toLowerCase();
    const tableRows = document.querySelectorAll('#booksTable tr');
    
    tableRows.forEach(row => {
        const title = row.querySelector('td:nth-child(2)');
        const author = row.querySelector('td:nth-child(3)');
        const genre = row.querySelector('td:nth-child(4)');
        const publisher = row.querySelector('td:nth-child(5)');
        
        if (title && author && genre && publisher) {
            const text = title.textContent.toLowerCase() + ' ' + 
                         author.textContent.toLowerCase() + ' ' + 
                         genre.textContent.toLowerCase() + ' ' + 
                         publisher.textContent.toLowerCase();
            
            if (text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Search members function
function searchMembers() {
    const searchTerm = document.getElementById('memberSearch').value.toLowerCase();
    const tableRows = document.querySelectorAll('#membersTable tr');
    
    tableRows.forEach(row => {
        const name = row.querySelector('td:nth-child(2)');
        const email = row.querySelector('td:nth-child(3)');
        const department = row.querySelector('td:nth-child(4)');
        const memberType = row.querySelector('td:nth-child(5)');
        
        if (name && email && department && memberType) {
            const text = name.textContent.toLowerCase() + ' ' + 
                         email.textContent.toLowerCase() + ' ' + 
                         department.textContent.toLowerCase() + ' ' + 
                         memberType.textContent.toLowerCase();
            
            if (text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Book operations
function viewBook(bookId) {
    // AJAX request to get book details
    fetch(`get_book.php?id=${bookId}`)
        .then(response => response.json())
        .then(data => {
            // Display book details in a modal
            alert(`Book Title: ${data.title}\nAuthor: ${data.author}\nGenre: ${data.genre}\nPublisher: ${data.publisher}\nStatus: ${data.status}`);
        })
        .catch(error => {
            console.error('Error fetching book details:', error);
        });
}

function editBook(bookId) {
    // Redirect to edit book page or open edit modal
    window.location.href = `edit_book.php?id=${bookId}`;
}

function viewAuthorBooks(authorId) {
    // Redirect to author books page
    window.location.href = `author_books.php?id=${authorId}`;
}

function viewPublisherBooks(publisherId) {
    // Redirect to publisher books page
    window.location.href = `publisher_books.php?id=${publisherId}`;
}

// Load dashboard data
function loadDashboardData() {
    // AJAX request to get dashboard statistics
    fetch('get_dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            // Update dashboard statistics
            document.getElementById('totalBooks').textContent = data.totalBooks;
            document.getElementById('totalMembers').textContent = data.totalMembers;
            document.getElementById('booksIssued').textContent = data.booksIssued;
            document.getElementById('overdue').textContent = data.overdue;
            
            // Update recent activities
            const activitiesTable = document.getElementById('recentActivities');
            activitiesTable.innerHTML = '';
            
            data.recentActivities.forEach(activity => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${activity.date}</td>
                    <td>${activity.activity}</td>
                    <td>${activity.user}</td>
                    <td>${activity.details}</td>
                `;
                activitiesTable.appendChild(row);
            });
        })
        .catch(error => {
            console.error('Error fetching dashboard data:', error);
        });
}