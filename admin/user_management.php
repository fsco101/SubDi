<?php
    include '../includes/header.php';
    
// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied. Only administrators can manage users.");
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        $sql = "UPDATE users SET status = 'inactive' WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $message = "User deactivated successfully.";
    }

    if (isset($_POST['activate_user'])) {
        $user_id = $_POST['user_id'];
        $sql = "UPDATE users SET status = 'active' WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $message = "User activated successfully.";
    }

    if (isset($_POST['update_role'])) {
        $user_id = $_POST['user_id'];
        $new_role = $_POST['role'];
        $sql = "UPDATE users SET role = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_role, $user_id);
        $stmt->execute();
        $message = "User role updated successfully.";
    }

    if (isset($_POST['delete_permanent'])) {
        $user_id = $_POST['user_id'];

        // Check if the user has active transactions
        $checkTransactions = "
            SELECT COUNT(*) AS total FROM (
                SELECT user_id FROM bookings WHERE user_id = ? AND status = 'pending'
                UNION ALL
                SELECT user_id FROM rentals WHERE user_id = ? AND status IN ('pending', 'approved')
                UNION ALL
                SELECT user_id FROM service_requests WHERE user_id = ? AND status IN ('pending', 'in-progress')
            ) AS active_transactions
        ";

        $stmt = $conn->prepare($checkTransactions);
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['total'] > 0) {
            $error = "User cannot be deleted because they have ongoing transactions.";
        } else {
            // Proceed with deletion if no transactions exist
            $deleteUser = "DELETE FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($deleteUser);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $message = "User permanently deleted.";
        }
    }
}

// Fetch all users
$sql = "SELECT user_id, f_name, l_name, email, phone_number, role, status, created_at, image_url FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);

// Count users by role
$roleStats = [
    'admin' => 0,
    'resident' => 0,

];

$statusStats = [
    'active' => 0,
    'inactive' => 0
];

if ($result && $result->num_rows > 0) {
    // Clone the result to avoid losing the original pointer
    $statResult = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($statResult as $user) {
        if (isset($roleStats[$user['role']])) {
            $roleStats[$user['role']]++;
        }
        
        if (isset($statusStats[$user['status']])) {
            $statusStats[$user['status']]++;
        }
    }
    // Reset result pointer
    $result->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="m-0 fs-3"><i class="fas fa-users-cog me-2"></i>User Management</h2>
                <span class="badge bg-light text-primary fs-5"><?= $result ? $result->num_rows : 0 ?> users</span>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="container-fluid px-0">
            <?php if (isset($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Row - Larger and more prominent -->
            <div class="row g-4 mb-4">
                <div class="col-lg-4 col-md-4">
                    <div class="stats-card primary text-center">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="number"><?= $result ? $result->num_rows : 0 ?></div>
                        <div class="label">Total Users</div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <div class="stats-card info text-center">
                        <div class="icon"><i class="fas fa-user-shield"></i></div>
                        <div class="number"><?= $roleStats['admin'] ?></div>
                        <div class="label">Admins</div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <div class="stats-card success text-center">
                        <div class="icon"><i class="fas fa-home"></i></div>
                        <div class="number"><?= $roleStats['resident'] ?></div>
                        <div class="label">Residents</div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters - Enhanced UI -->
            <div class="row mb-4 align-items-center">
                <div class="col-lg-6 col-md-6 mb-3 mb-md-0">
                    <div class="search-form">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="userSearch" class="form-control border-start-0 py-2" 
                                   placeholder="Search by name, email or phone...">
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="d-flex gap-3 justify-content-md-end">
                        <select id="roleFilter" class="form-select filter-dropdown">
                            <option value="all">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="resident">Resident</option>
                        </select>
                        <select id="statusFilter" class="form-select filter-dropdown">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Main Card - Full width table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="m-0 fs-4"><i class="fas fa-table me-2"></i>Users Directory</h5>
                        <div>
                            <button class="btn btn-outline-light me-2" id="refreshTable">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th data-sort="id" data-direction="asc">#</th>
                                    <th data-sort="name" data-direction="asc">User</th>
                                    <th data-sort="contact" data-direction="asc">Contact</th>
                                    <th data-sort="role" data-direction="asc">Role</th>
                                    <th class="text-center" data-sort="status" data-direction="asc">Status</th>
                                    <th data-sort="date" data-direction="asc">Registered</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php $counter = 0; ?>
                                    <?php while ($user = $result->fetch_assoc()): ?>
                                        <?php $counter++; ?>
                                        <tr class="user-row" data-role="<?= $user['role']; ?>" data-status="<?= $user['status']; ?>">
                                            <td class="text-center"><?= $counter ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($user['image_url']): ?>
                                                        <img src="<?= htmlspecialchars($user['image_url']); ?>" alt="User Image" class="user-image me-2">
                                                    <?php else: ?>
                                                        <i class="fas fa-user-circle fa-lg text-secondary me-2"></i>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-medium"><?= htmlspecialchars($user['f_name'] . ' ' . $user['l_name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><i class="fas fa-envelope fa-sm text-muted me-1"></i> <?= htmlspecialchars($user['email']); ?></div>
                                                <div><i class="fas fa-phone fa-sm text-muted me-1"></i> <?= htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-flex align-items-center gap-1">
                                                    <input type="hidden" name="user_id" value="<?= $user['user_id']; ?>">
                                                    <select name="role" class="role-select">
                                                        <option value="resident" <?= $user['role'] === 'resident' ? 'selected' : ''; ?>>Resident</option>
                                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                    </select>
                                                    <button type="submit" name="update_role" class="btn btn-xs btn-outline-primary custom-tooltip">
                                                        <i class="fas fa-save"></i>
                                                        <span class="tooltip-text">Update Role</span>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $user['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?> rounded-pill px-3">
                                                    <?= ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($user['status'] === 'active'): ?>
                                                        <form method="POST">
                                                            <input type="hidden" name="user_id" value="<?= $user['user_id']; ?>">
                                                            <button type="submit" name="delete_user" class="btn btn-xs btn-warning text-white custom-tooltip" 
                                                                    onclick="return confirm('Deactivate this user?')">
                                                                <i class="fas fa-user-slash"></i>
                                                                <span class="tooltip-text">Deactivate</span>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST">
                                                            <input type="hidden" name="user_id" value="<?= $user['user_id']; ?>">
                                                            <button type="submit" name="activate_user" class="btn btn-xs btn-success custom-tooltip">
                                                                <i class="fas fa-user-check"></i>
                                                                <span class="tooltip-text">Activate</span>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>


                                                    
                    
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-3">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-6 text-muted">Showing <span id="showing-count" class="fw-bold"><?= $result ? $result->num_rows : 0 ?></span> users</span>
                    </div>
                    <div>
                        <span class="badge bg-success fs-6 me-2 p-2"><?= $statusStats['active'] ?> Active</span>
                        <span class="badge bg-danger fs-6 p-2"><?= $statusStats['inactive'] ?> Inactive</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-dismiss alerts after 5 seconds
    window.setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Get filter elements
    const userSearch = document.getElementById('userSearch');
    const userRows = document.querySelectorAll('.user-row');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    // Combined filter function to apply all filters at once
    function applyAllFilters() {
        const searchTerm = userSearch.value.toLowerCase();
        const roleValue = roleFilter.value;
        const statusValue = statusFilter.value;
        
        let visibleCount = 0;
        
        userRows.forEach(row => {
            // Get searchable content (user name, email, phone)
            const nameCell = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const contactCell = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const searchContent = nameCell + ' ' + contactCell;
            
            // Get row attributes for filtering
            const rowRole = row.getAttribute('data-role');
            const rowStatus = row.getAttribute('data-status');
            
            // Apply all filters
            const searchMatch = searchTerm === '' || searchContent.includes(searchTerm);
            const roleMatch = roleValue === 'all' || rowRole === roleValue;
            const statusMatch = statusValue === 'all' || rowStatus === statusValue;
            
            // Show/hide row based on combined filter results
            if (searchMatch && roleMatch && statusMatch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update counter
        document.getElementById('showing-count').textContent = visibleCount;
        
        // Highlight active filters
        highlightActiveFilters();
    }
    
    // Add visual indication of active filters
    function highlightActiveFilters() {
        // Reset classes
        roleFilter.classList.remove('border-primary');
        statusFilter.classList.remove('border-primary');
        userSearch.classList.remove('border-primary');
        
        // Add active class to role filter if not "all"
        if (roleFilter.value !== 'all') {
            roleFilter.classList.add('border-primary');
        }
        
        // Add active class to status filter if not "all"
        if (statusFilter.value !== 'all') {
            statusFilter.classList.add('border-primary');
        }
        
        // Add active class to search if not empty
        if (userSearch.value.trim() !== '') {
            userSearch.classList.add('border-primary');
        }
    }
    
    // Set event listeners
    userSearch.addEventListener('input', applyAllFilters);
    roleFilter.addEventListener('change', applyAllFilters);
    statusFilter.addEventListener('change', applyAllFilters);
    
    // Refresh button resets filters and reloads page
    document.getElementById('refreshTable').addEventListener('click', function() {
        // Reset filters before reload to ensure clean state
        userSearch.value = '';
        roleFilter.value = 'all';
        statusFilter.value = 'all';
        
        // Reload page after a short delay
        setTimeout(() => {
            window.location.reload();
        }, 500);
    });
    
    
    // Enable sort functionality for table headers
    document.querySelectorAll('th[data-sort]').forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const sortBy = this.getAttribute('data-sort');
            const sortDirection = this.getAttribute('data-direction') === 'asc' ? 'desc' : 'asc';
            
            sortTable(sortBy, sortDirection);
            
            // Update direction for next click
            this.setAttribute('data-direction', sortDirection);
            
            // Update visual indicator
            document.querySelectorAll('th[data-sort]').forEach(h => {
                h.querySelector('i.sort-icon')?.remove();
            });
            
            const iconClass = sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
            this.innerHTML += ` <i class="fas ${iconClass} sort-icon text-white-50"></i>`;
        });
    });
    
    // Function to sort table rows
    function sortTable(sortBy, direction) {
        const tbody = document.querySelector('#usersTable tbody');
        const rows = Array.from(tbody.querySelectorAll('tr.user-row'));
        
        rows.sort((a, b) => {
            let aValue, bValue;
            
            switch(sortBy) {
                case 'id':
                    aValue = parseInt(a.querySelector('td:nth-child(1)').textContent);
                    bValue = parseInt(b.querySelector('td:nth-child(1)').textContent);
                    break;
                case 'name':
                    aValue = a.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
                    bValue = b.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
                    break;
                case 'contact':
                    aValue = a.querySelector('td:nth-child(3)').textContent.trim().toLowerCase();
                    bValue = b.querySelector('td:nth-child(3)').textContent.trim().toLowerCase();
                    break;
                case 'role':
                    aValue = a.getAttribute('data-role');
                    bValue = b.getAttribute('data-role');
                    break;
                case 'status':
                    aValue = a.getAttribute('data-status');
                    bValue = b.getAttribute('data-status');
                    break;
                case 'date':
                    aValue = new Date(a.querySelector('td:nth-child(6)').textContent);
                    bValue = new Date(b.querySelector('td:nth-child(6)').textContent);
                    break;
                default:
                    aValue = a.querySelector('td:nth-child(1)').textContent;
                    bValue = b.querySelector('td:nth-child(1)').textContent;
            }
            
            if (direction === 'asc') {
                return aValue > bValue ? 1 : -1;
            } else {
                return aValue < bValue ? 1 : -1;
            }
        });
        
        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
    }
    
    // Run initial filter to set up correct counts
    applyAllFilters();
});
</script>
</body>
</html>

<?php $conn->close(); ?>

<style>
:root {
    --primary: #2c3e50;
    --secondary: #34495e;
    --light: #f8f9fa;
    --accent: #3498db;
    --success: #2ecc71;
    --danger: #e74c3c;
    --warning: #f39c12;
}

/* Base styles */
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    background-color: #f5f5f5;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-size: 16px;
}

/* Header */
.admin-header {
    background-color: var(--primary);
    color: white;
    padding: 1rem 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 100;
    width: 100%;
}

/* Container */
.container-fluid {
    padding: 0 1rem;
    width: 100%;
}

/* Content area */
.main-content {
    padding: 1rem;
}

/* User image */
.user-image {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid #fff;
}

/* Action buttons */
.action-buttons { 
    display: flex; 
    gap: 8px; 
}

/* Table styles */
.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    font-weight: 600;
    padding: 0.75rem;
    background-color: var(--secondary);
    color: white;
    text-align: left;
}

.table td {
    padding: 0.75rem;
    border-bottom: 1px solid #eee;
}

/* Form elements */
.role-select {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0.375rem;
    font-size: 0.9rem;
}

/* Buttons */
.btn {
    font-weight: 500;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
}

.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
}

.btn-success {
    background-color: var(--success);
    color: white;
}

.btn-danger {
    background-color: var(--danger);
    color: white;
}

/* Cards */
.card {
    background-color: white;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1rem;
}

.card-header {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1rem;
}

.card-footer {
    background-color: var(--light);
    padding: 0.75rem 1rem;
    border-top: 1px solid #eee;
}

/* Stats card */
.stats-card {
    background-color: white;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1rem;
    height: 100%;
    border-left: 3px solid var(--accent);
}

.stats-card .icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--accent);
}

.stats-card .number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary);
}

.stats-card .label {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Search and filters */
.search-form {
    margin-bottom: 1rem;
    min-height: 50px; /* Prevents collapse */
}

.search-form input {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    min-width: 200px; /* Prevents collapse */
}

.filter-dropdown {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0.5rem;
    min-width: 120px; /* Prevents collapse */
}

/* Form container - prevent collapse */
.form-container {
    min-height: 100px;
}

/* Badges */
.role-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    display: inline-block;
}

.role-badge-admin {
    background-color: var(--accent);
    color: white;
}

.role-badge-resident {
    background-color: var(--success);
    color: white;
}

/* Table container */
.table-responsive {
    overflow-x: auto;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    min-height: 200px; /* Ensures minimum height even when empty */
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 2rem;
    background-color: white;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin: 1rem 0;
    min-height: 150px; /* Ensures visibility */
}

.empty-state-message {
    color: #6c757d;
    font-size: 1rem;
}

/* Simple alert styles */
.alert {
    border-radius: 4px;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
}

/* Loading indicator */
.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid var(--accent);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.loading-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 200px; /* Ensures visibility */
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Table wrapper - prevent collapse */
.table-wrapper {
    min-height: 250px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .container-fluid {
        padding: 0 0.75rem;
    }
    
    .main-content {
        padding: 0.75rem;
    }
    
    .stats-card .number {
        font-size: 1.25rem;
    }
    
    /* Maintain form size on mobile */
    .search-form input {
        min-width: 150px;
    }
}
</style>
