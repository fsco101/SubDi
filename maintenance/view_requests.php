<?php
include '../includes/header.php';


    $query = "SELECT sr.request_id, sr.user_id, sr.category, sr.description, sr.status, sr.created_at, 
    u.f_name, u.l_name 
FROM service_requests sr 
JOIN users u ON sr.user_id = u.user_id 
ORDER BY sr.created_at DESC";

// After (fixed code):
// Get the current logged-in user ID from the session
if (!isset($_SESSION['user_id'])) {
// Redirect to login page if user is not logged in
header("Location: ../login.php");
exit;
}

$user_id = $_SESSION['user_id'];

$query = "SELECT sr.request_id, sr.user_id, sr.category, sr.description, sr.status, sr.created_at, 
    u.f_name, u.l_name 
FROM service_requests sr 
JOIN users u ON sr.user_id = u.user_id 
WHERE sr.user_id = ?
ORDER BY sr.created_at DESC";

if ($stmt = $conn->prepare($query)) {
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
} else {
die("Query error: " . $conn->error);
}

// Define status classes for better visual representation
$statusClasses = [
    'pending' => 'status-pending',
    'in-progress' => 'status-progress',
    'completed' => 'status-completed',
    'cancelled' => 'status-cancelled'
];

// Set page title for better SEO and browser tab display
$pageTitle = "Service Requests Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    /* ------------ Base & Reset ------------ */
    :root {
        --primary: #3b82f6;
        --primary-dark: #2563eb;
        --primary-light: #dbeafe;
        --secondary: #64748b;
        --danger: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
        --info: #0ea5e9;
        --light: #f8fafc;
        --dark: #1e293b;
        --background: #f1f5f9;
        --border: #e2e8f0;
        --text: #334155;
        --text-light: #64748b;
        --transition: all 0.3s ease;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --radius: 0.5rem;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        background-color: var(--background);
        color: var(--text);
        font-family: 'Poppins', 'Segoe UI', sans-serif;
        line-height: 1.6;
    }

    /* ------------ Layout & Container ------------ */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
        margin-top: 80px; /* Space for fixed header */
    }

    /* ------------ Header ------------ */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        background-color: white;
        padding: 1.5rem 2rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
    }

    .dashboard-header h1 {
        font-size: 1.8rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--dark);
    }

    .dashboard-header h1 i {
        color: var(--primary);
    }

    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    /* ------------ Search Bar ------------ */
    .search-container {
        position: relative;
    }

    .search-container input {
        background-color: var(--light);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        color: var(--text);
        font-size: 0.9rem;
        width: 250px;
        transition: var(--transition);
    }

    .search-container input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
    }

    .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
    }

    /* ------------ Table Styling ------------ */
    .table-container {
        overflow-x: auto;
        border-radius: var(--radius);
        background-color: white;
        box-shadow: var(--shadow);
        margin-top: 1.5rem;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    thead {
        background-color: var(--light);
        border-bottom: 2px solid var(--border);
    }

    th {
        padding: 1.25rem 1rem;
        font-weight: 600;
        font-size: 0.9rem;
        color: var (--text);
        cursor: pointer;
        background-color: #f8fafc;
        position: relative;
    }

    th i {
        margin-left: 0.5rem;
        font-size: 0.8rem;
        color: var(--text-light);
        transition: var(--transition);
    }


    td {
        padding: 1.25rem 1rem;
        font-size: 0.95rem;
        color: var(--text);
        background-color: white;
        vertical-align: middle;
    }

    /* Column widths */
    th:nth-child(1), td:nth-child(1) { width: 15%; }
    th:nth-child(2), td:nth-child(2) { width: 15%; }
    th:nth-child(3), td:nth-child(3) { width: 30%; }
    th:nth-child(4), td:nth-child(4) { width: 15%; }
    th:nth-child(5), td:nth-child(5) { width: 15%; }
    th:nth-child(6), td:nth-child(6) { width: 10%; }

    /* ------------ Description cell handling ------------ */
    .description-cell {
        max-width: 300px;
    }

    .truncate-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
        position: relative;
    }

    .truncate-text:hover::after {
        content: attr(title);
        position: absolute;
        left: 0;
        top: 100%;
        background-color: white;
        color: var(--text);
        padding: 0.75rem;
        border-radius: var(--radius);
        z-index: 10;
        width: 300px;
        white-space: normal;
        font-size: 0.85rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border);
    }

    /* ------------ Status Badges ------------ */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        text-transform: capitalize;
        line-height: 1;
    }

    .status-badge::before {
        content: "";
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }

    .status-pending {
        background-color: #fff7ed;
        color: var(--warning);
        border: 1px solid #ffedd5;
    }

    .status-pending::before {
        background-color: var(--warning);
    }

    .status-progress {
        background-color: #eff6ff;
        color: var(--info);
        border: 1px solid #dbeafe;
    }

    .status-progress::before {
        background-color: var(--info);
    }

    .status-completed {
        background-color: #ecfdf5;
        color: var(--success);
        border: 1px solid #d1fae5;
    }

    .status-completed::before {
        background-color: var(--success);
    }

    .status-cancelled {
        background-color: #fef2f2;
        color: var(--danger);
        border: 1px solid #fee2e2;
    }

    .status-cancelled::before {
        background-color: var(--danger);
    }

    /* ------------ Action Buttons ------------ */
    .action-buttons {
        display: flex;
        gap: 0.75rem;
    }

    .action-buttons a {
        width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius);
        transition: var(--transition);
    }

    .edit-btn {
        background-color: #fff7ed;
        color: var(--warning);
    }

    .edit-btn:hover {
        background-color: var(--warning);
        color: white;
    }

    .delete-btn {
        background-color: #fef2f2;
        color: var(--danger);
    }

    .delete-btn:hover {
        background-color: var(--danger);
        color: white;
    }

    /* ------------ Empty State ------------ */
    .empty-state {
        text-align: center;
        padding: 4rem 1rem;
        background-color: white;
        border-radius: var (--radius);
        box-shadow: var(--shadow);
        margin-top: 1.5rem;
    }

    .empty-state i {
        color: var(--text-light);
        margin-bottom: 1.5rem;
        opacity: 0.7;
    }

    .empty-state p {
        color: var(--text);
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
    }

    /* ------------ Modal ------------ */
    .modal {
        display: none;
        position: fixed;
        z-index: 999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px);
    }

    .modal-content {
        background-color: white;
        margin: 15% auto;
        padding: 2rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        width: 90%;
        max-width: 500px;
        animation: modalFade 0.3s;
    }

    @keyframes modalFade {
        from {opacity: 0; transform: translateY(-20px);}
        to {opacity: 1; transform: translateY(0);}
    }

    .close-modal {
        color: var(--text-light);
        float: right;
        font-size: 1.5rem;
        cursor: pointer;
        transition: var(--transition);
    }

    .close-modal:hover {
        color: var(--dark);
    }

    .modal h3 {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--dark);
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .modal h3 i {
        color: var(--danger);
    }

    .modal p {
        margin-bottom: 1.5rem;
        color: var(--text);
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
    }

    /* ------------ Buttons ------------ */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius);
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        text-decoration: none;
        transition: var(--transition);
        border: none;
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
        box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .btn-secondary {
        background-color: white;
        color: var(--dark);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background-color: var(--light);
        border-color: var(--text-light);
    }

    .btn-danger {
        background-color: var(--danger);
        color: white;
    }

    .btn-danger:hover {
        background-color: #b91c1c;
    }

    .btn-outline {
        background-color: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
    }

    .btn-outline:hover {
        background-color: var(--primary);
        color: white;
    }

    /* ------------ Pagination ------------ */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .pagination-item {
        width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background-color: white;
        color: var(--text);
        font-size: 0.9rem;
        cursor: pointer;
        transition: var(--transition);
    }

    .pagination-item:hover, .pagination-item.active {
        background-color: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .pagination-item.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ------------ Responsive ------------ */
    @media (max-width: 992px) {
        .container {
            padding: 1.5rem;
        }
        
        .dashboard-header {
            padding: 1.25rem;
        }
    }

    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .header-actions {
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        
        .search-container {
            width: 100%;
        }
        
        .search-container input {
            width: 100%;
        }
        
        .description-cell {
            max-width: 200px;
        }
        
        .btn {
            width: 100%;
        }
        
        .table-container {
            border-radius: var(--radius);
            overflow-x: auto;
        }
        
        table {
            min-width: 800px;
        }
        
        .empty-state {
            padding: 3rem 1rem;
        }
    }

    @media (max-width: 576px) {
        .container {
            padding: 1rem;
        }
        
        .dashboard-header {
            padding: 1rem;
        }
        
        .dashboard-header h1 {
            font-size: 1.5rem;
        }
        
        th, td {
            padding: 0.75rem 0.5rem;
            font-size: 0.85rem;
        }
        
        .status-badge {
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .action-buttons a {
            width: 2rem;
            height: 2rem;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1><i class="fas fa-ticket-alt"></i> Service Requests</h1>
            <div class="header-actions">
                <a href="/subdisystem/maintenance/create_request.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Request</a>
                <div class="search-container">
                    <input type="text" id="requestSearch" placeholder="Search requests...">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
        </header>

        <main>
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox fa-4x"></i>
                    <p>No service requests found</p>
                    <a href="/subdisystem/maintenance/create_request.php" class="btn btn-outline">Create your first request</a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table id="requestsTable">
                        <thead>
                            <tr>
                                <th>User <i class="fas fa-sort"></i></th>
                                <th>Category <i class="fas fa-sort"></i></th>
                                <th>Description</th>
                                <th>Status <i class="fas fa-sort"></i></th>
                                <th>Created <i class="fas fa-sort"></i></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?= htmlspecialchars($request['f_name'] . ' ' . $request['l_name']); ?></td>
                                    <td><?= htmlspecialchars($request['category']); ?></td>
                                    <td class="description-cell">
                                        <div class="truncate-text" title="<?= htmlspecialchars($request['description']); ?>">
                                            <?= htmlspecialchars($request['description']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $statusClasses[strtolower($request['status'])] ?? ''; ?>">
                                            <?= htmlspecialchars($request['status']); ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if (strtolower($request['status']) !== 'completed'): ?>
                                                <a href="edit_request.php?id=<?= $request['request_id']; ?>" class="edit-btn" title="Edit request">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="javascript:void(0)" class="delete-btn" 
                                               onclick="confirmDelete(<?= $request['request_id']; ?>)" title="Delete request">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add pagination if needed -->
                <?php if (count($requests) > 10): ?>
                <div class="pagination">
                    <a href="#" class="pagination-item disabled"><i class="fas fa-chevron-left"></i></a>
                    <a href="#" class="pagination-item active">1</a>
                    <a href="#" class="pagination-item">2</a>
                    <a href="#" class="pagination-item">3</a>
                    <a href="#" class="pagination-item"><i class="fas fa-chevron-right"></i></a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal for delete confirmation -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
            <p>Are you sure you want to delete this service request? This action cannot be undone.</p>
            <div class="modal-actions">
                <button id="cancelDelete" class="btn btn-secondary">Cancel</button>
                <a id="confirmDeleteBtn" href="#" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        const searchInput = document.getElementById('requestSearch');
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#requestsTable tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Delete modal functionality
        const modal = document.getElementById('deleteModal');
        const closeModal = document.querySelector('.close-modal');
        const cancelDelete = document.getElementById('cancelDelete');
        
        window.confirmDelete = function(requestId) {
            modal.style.display = 'block';
            document.getElementById('confirmDeleteBtn').href = `delete_request.php?id=${requestId}`;
        }
        
        closeModal.onclick = function() {
            modal.style.display = 'none';
        }
        
        cancelDelete.onclick = function() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Table sorting functionality
        document.querySelectorAll('th').forEach((header, index) => {
            if (index !== 2 && index !== 5) { // Skip description and actions columns
                header.addEventListener('click', () => {
                    sortTable(index);
                });
            }
        });
        
        function sortTable(column) {
            const table = document.getElementById('requestsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Get the current direction
            const currentDir = table.dataset.sortDir === 'asc' ? 'desc' : 'asc';
            table.dataset.sortDir = currentDir;
            
            // Sort the rows
            rows.sort((a, b) => {
                const cellA = a.querySelectorAll('td')[column].textContent.trim();
                const cellB = b.querySelectorAll('td')[column].textContent.trim();
                
                if (currentDir === 'asc') {
                    return cellA.localeCompare(cellB);
                } else {
                    return cellB.localeCompare(cellA);
                }
            });
            
            // Clear and re-append rows
            while (tbody.firstChild) {
                tbody.removeChild(tbody.firstChild);
            }
            
            rows.forEach(row => {
                tbody.appendChild(row);
            });
        }
    });
    </script>
</body>
</html>
<?php include '../includes/footer.php'; ?>