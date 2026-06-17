// JavaScript for main.php

document.addEventListener('DOMContentLoaded', function() {
    // Show dashboard by default
    showTab('dashboard');
    
    // Initialize date inputs with today's date
    const today = new Date();
    const nextMonth = new Date(today);
    nextMonth.setDate(today.getDate() + 30); // Set due date to 30 days from now by default
    
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (input.id === 'due_date') {
            input.valueAsDate = nextMonth;
        } else {
            input.valueAsDate = today;
        }
    });
    
    // Initialize search functionality
    initializeSearch();
    
    // Load circulation data
    loadCirculationData();
    
    // Load book requests data
    loadBookRequestsData();
});

// Initialize search functionality
function initializeSearch() {
    // Add event listeners to search inputs
    const searchInputs = document.querySelectorAll('input[id$="-search"]');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', function() {
            const searchType = this.id.split('-')[0];
            switch(searchType) {
                case 'book':
                    searchBooks();
                    break;
                case 'member':
                    searchMembers();
                    break;
                case 'circulation':
                    searchCirculation();
                    break;
                case 'request':
                    searchRequests();
                    break;
            }
        });
    });
}

// Show tab content
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

// Show book tab content
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

// Show circulation tab content
function showCirculationTab(tabId) {
    // Hide all circulation tab contents
    document.querySelectorAll('#circulation .tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show the selected circulation tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Update active state in circulation tabs
    document.querySelectorAll('#circulation .tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.getAttribute('onclick') && tab.getAttribute('onclick').includes(tabId)) {
            tab.classList.add('active');
        }
    });
}

// Show report tab content
function showReportTab(tabId) {
    // Hide all report tab contents
    document.querySelectorAll('#reports .tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show the selected report tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Update active state in report tabs
    document.querySelectorAll('#reports .tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.getAttribute('onclick') && tab.getAttribute('onclick').includes(tabId)) {
            tab.classList.add('active');
        }
    });
}

// Show setting tab content
function showSettingTab(tabId) {
    // Hide all setting tab contents
    document.querySelectorAll('#settings .tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show the selected setting tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Update active state in setting tabs
    document.querySelectorAll('#settings .tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.getAttribute('onclick') && tab.getAttribute('onclick').includes(tabId)) {
            tab.classList.add('active');
        }
    });
}

// Search functions
function searchBooks() {
    const searchTerm = document.getElementById('book-search').value.toLowerCase();
    const tableRows = document.querySelectorAll('#books-list table tbody tr');
    
    tableRows.forEach(row => {
        const title = row.cells[0].textContent.toLowerCase();
        const author = row.cells[1].textContent.toLowerCase();
        const publisher = row.cells[2].textContent.toLowerCase();
        const isbn = row.cells[3].textContent.toLowerCase();
        
        if (title.includes(searchTerm) || author.includes(searchTerm) || 
            publisher.includes(searchTerm) || isbn.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchMembers() {