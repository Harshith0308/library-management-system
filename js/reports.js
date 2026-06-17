// Reports JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    // Initialize reports data
    loadBooksReport();
    loadMembersReport();
    loadCirculationReport();
    loadFineReport();
});

// Load books report data
function loadBooksReport() {
    // AJAX request to get books report data
    fetch('reports.php?report=books')
        .then(response => response.json())
        .then(data => {
            // Update books report content
            const booksReportDiv = document.getElementById('books-report');
            if (booksReportDiv) {
                // Create statistics section
                let statsHtml = `
                    <div class="report-stats">
                        <div class="stat-card">
                            <h3>${data.totalBooks}</h3>
                            <p>Total Books</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.availableBooks}</h3>
                            <p>Available Books</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.issuedBooks}</h3>
                            <p>Issued Books</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.overdueBooks}</h3>
                            <p>Overdue Books</p>
                        </div>
                    </div>
                `;
                
                // Create genre distribution section
                statsHtml += `
                    <h3>Books by Genre</h3>
                    <div class="report-chart">
                        <table>
                            <thead>
                                <tr>
                                    <th>Genre</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.genreDistribution.forEach(genre => {
                    statsHtml += `
                        <tr>
                            <td>${genre.name}</td>
                            <td>${genre.count}</td>
                            <td>${genre.percentage}%</td>
                        </tr>
                    `;
                });
                
                statsHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                // Create most popular books section
                statsHtml += `
                    <h3>Most Popular Books</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Times Borrowed</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.popularBooks.forEach(book => {
                    statsHtml += `
                        <tr>
                            <td>${book.title}</td>
                            <td>${book.author}</td>
                            <td>${book.borrowCount}</td>
                        </tr>
                    `;
                });
                
                statsHtml += `
                            </tbody>
                        </table>
                `;
                
                booksReportDiv.innerHTML = statsHtml;
            }
        })
        .catch(error => {
            console.error('Error fetching books report data:', error);
        });
}

// Load members report data
function loadMembersReport() {
    // AJAX request to get members report data
    fetch('reports.php?report=members')
        .then(response => response.json())
        .then(data => {
            // Update members report content
            const membersReportDiv = document.getElementById('members-report');
            if (membersReportDiv) {
                // Create statistics section
                let statsHtml = `
                    <div class="report-stats">
                        <div class="stat-card">
                            <h3>${data.totalMembers}</h3>
                            <p>Total Members</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.activeMembers}</h3>
                            <p>Active Members</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.newMembers}</h3>
                            <p>New Members (This Month)</p>
                        </div>
                    </div>
                `;
                
                // Create member type distribution section
                statsHtml += `
                    <h3>Members by Type</h3>
                    <div class="report-chart">
                        <table>
                            <thead>
                                <tr>
                                    <th>Member Type</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.memberTypeDistribution.forEach(type => {
                    statsHtml += `
                        <tr>
                            <td>${type.name}</td>
                            <td>${type.count}</td>
                            <td>${type.percentage}%</td>
                        </tr>
                    `;
                });
                
                statsHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                // Create most active members section
                statsHtml += `
                    <h3>Most Active Members</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Books Borrowed</th>
                                <th>Last Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.activeMembers.forEach(member => {
                    statsHtml += `
                        <tr>
                            <td>${member.name}</td>
                            <td>${member.borrowCount}</td>
                            <td>${member.lastActivity}</td>
                        </tr>
                    `;
                });
                
                statsHtml += `
                            </tbody>
                        </table>
                `;
                
                membersReportDiv.innerHTML = statsHtml;
            }
        })
        .catch(error => {
            console.error('Error fetching members report data:', error);
        });
}

// Load circulation report data
function loadCirculationReport() {
    // AJAX request to get circulation report data
    fetch('reports.php?report=circulation')
        .then(response => response.json())
        .then(data => {
            // Update circulation report content
            const circulationReportDiv = document.getElementById('circulation-report');
            if (circulationReportDiv) {
                // Create statistics section
                let statsHtml = `
                    <div class="report-stats">
                        <div class="stat-card">
                            <h3>${data.totalBorrowed}</h3>
                            <p>Total Books Borrowed</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.totalReturned}</h3>
                            <p>Total Books Returned</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.currentlyBorrowed}</h3>
                            <p>Currently Borrowed</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.overdueBooks}</h3>
                            <p>Overdue Books</p>
                        </div>
                    </div>
                `;
                
                // Create monthly circulation chart
                statsHtml += `
                    <h3>Monthly Circulation</h3>
                    <div class="report-chart">
                        <table>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Books Borrowed</th>
                                    <th>Books Returned</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.monthlyCirculation.forEach(month => {
                    statsHtml += `
                        <tr>
                            <td>${month.name}</td>
                            <td>${month.borrowed}</td>
                            <td>${month.returned}</td>
                        </tr>
                    `;
                });
                
                statsHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                circulationReportDiv.innerHTML = statsHtml;
            }
        })
        .catch(error => {
            console.error('Error fetching circulation report data:', error);
        });
}

// Load fine report data
function loadFineReport() {
    // AJAX request to get fine report data
    fetch('reports.php?report=fines')
        .then(response => response.json())
        .then(data => {
            // Update fine report content
            const fineReportDiv = document.getElementById('fine-report');
            if (fineReportDiv) {
                // Create statistics section
                let statsHtml = `
                    <div class="report-stats">
                        <div class="stat-card">
                            <h3>₹${data.totalFines}</h3>
                            <p>Total Fines</p>
                        </div>
                        <div class="stat-card">
                            <h3>₹${data.collectedFines}</h3>
                            <p>Collected Fines</p>
                        </div>
                        <div class="stat-card">
                            <h3>₹${data.pendingFines}</h3>
                            <p>Pending Fines</p>
                        </div>
                    </div>
                `;
                
                // Create monthly fine chart
                statsHtml += `
                    <h3>Monthly Fine Collection</h3>
                    <div class="report-chart">
                        <table>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Fines Generated</th>
                                    <th>Fines Collected</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.monthlyFines.forEach(month => {
                    statsHtml += `
                        <tr>
                            <td>${month.name}</td>
                            <td>₹${month.generated}</td>
                            <td>₹${month.collected}</td>
                        </tr>
                    `;
                });
                
                statsHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                // Create members with highest fines section
                statsHtml += `
                    <h3>Members with Highest Fines</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Member Name</th>
                                <th>Total Fine</th>
                                <th>Paid</th>
                                <th>Pending</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.membersFines.forEach(member => {
                    statsHtml += `
                        <tr>
                            <td>${member.name}</td>
                            <td>₹${member.totalFine}</td>
                            <td>₹${member.paidFine}</td>
                            <td>₹${member.pendingFine}</td>
                        </tr>
                    `;
                });
                
                statsHtml += `
                            </tbody>
                        </table>
                `;
                
                fineReportDiv.innerHTML = statsHtml;
            }
        })
        .catch(error => {
            console.error('Error fetching fine report data:', error);
        });
}