// Circulation and Book Requests JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    // Initialize circulation data
    loadCirculationData();
    
    // Initialize book requests data
    loadBookRequestsData();
    
    // Add event listeners for circulation forms
    const issueBookForm = document.querySelector('#issue-book form');
    if (issueBookForm) {
        issueBookForm.addEventListener('submit', function(e) {
            // Form validation can be added here
        });
    }
    
    const returnBookForm = document.querySelector('#return-book form');
    if (returnBookForm) {
        returnBookForm.addEventListener('submit', function(e) {
            // Form validation can be added here
        });
    }
});

// Load circulation data
function loadCirculationData() {
    // AJAX request to get circulation data
    fetch('get_circulation_data.php?action=get_circulation_data')
        .then(response => response.json())
        .then(data => {
            // Update circulation history table
            const circulationTable = document.querySelector('#circulation-history table tbody');
            if (circulationTable) {
                circulationTable.innerHTML = '';
                
                data.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.book_title}</td>
                        <td>${item.member_name}</td>
                        <td>${item.issue_date}</td>
                        <td>${item.due_date}</td>
                        <td>${item.return_date || 'Not returned'}</td>
                        <td>${item.status}</td>
                        <td>${item.fine || '0.00'}</td>
                    `;
                    circulationTable.appendChild(row);
                });
            }
            
            // Update return book dropdown
            const lendingSelect = document.getElementById('lending_id');
            if (lendingSelect) {
                lendingSelect.innerHTML = '<option value="">Select Book</option>';
                
                data.filter(item => item.status === 'Issued').forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.lending_id;
                    option.textContent = `${item.book_title} (${item.member_name})`;
                    lendingSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error fetching circulation data:', error);
        });
}

// Load book requests data
function loadBookRequestsData() {
    // AJAX request to get book requests data
    fetch('get_requests_data.php?action=get_requests_data')
        .then(response => response.json())
        .then(data => {
            // Update book requests table
            const requestsTable = document.querySelector('#requests table tbody');
            if (requestsTable) {
                requestsTable.innerHTML = '';
                
                data.forEach(request => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${request.title}</td>
                        <td>${request.requested_by}</td>
                        <td>${request.request_date}</td>
                        <td>${request.status}</td>
                        <td>
                            <a href="book_requests.php?action=view&id=${request.request_id}" class="btn">View</a>
                            <a href="book_requests.php?action=edit&id=${request.request_id}" class="btn btn-green">Edit</a>
                            <a href="book_requests.php?action=delete&id=${request.request_id}" class="btn btn-red" onclick="return confirm('Are you sure you want to delete this request?')">Delete</a>
                        </td>
                    `;
                    requestsTable.appendChild(row);
                });
            }
        })
        .catch(error => {
            console.error('Error fetching book requests data:', error);
        });
}

// Search functions
function searchMembers() {
    const searchTerm = document.getElementById('member-search').value.toLowerCase();
    const tableRows = document.querySelectorAll('#members table tbody tr');
    
    tableRows.forEach(row => {
        const name = row.cells[0].textContent.toLowerCase();
        const email = row.cells[1].textContent.toLowerCase();
        const department = row.cells[2].textContent.toLowerCase();
        
        if (name.includes(searchTerm) || email.includes(searchTerm) || department.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchCirculation() {
    const searchTerm = document.getElementById('circulation-search').value.toLowerCase();
    const tableRows = document.querySelectorAll('#circulation-history table tbody tr');
    
    tableRows.forEach(row => {
        const book = row.cells[0].textContent.toLowerCase();
        const member = row.cells[1].textContent.toLowerCase();
        const status = row.cells[5].textContent.toLowerCase();
        
        if (book.includes(searchTerm) || member.includes(searchTerm) || status.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchRequests() {
    const searchTerm = document.getElementById('request-search').value.toLowerCase();
    const tableRows = document.querySelectorAll('#requests table tbody tr');
    
    tableRows.forEach(row => {
        const title = row.cells[0].textContent.toLowerCase();
        const requestedBy = row.cells[1].textContent.toLowerCase();
        const status = row.cells[3].textContent.toLowerCase();
        
        if (title.includes(searchTerm) || requestedBy.includes(searchTerm) || status.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}