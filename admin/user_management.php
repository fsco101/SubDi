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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --admin-primary: #2c3e50;
            --admin-secondary: #34495e;
            --admin-light: #ecf0f1;
            --admin-accent: #3498db;
            --admin-success: #2ecc71;
            --admin-danger: #e74c3c;
            --admin-warning: #f39c12;
            --admin-info: #3498db;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.875rem;
        }
        
        .admin-header {
            background-color: var(--admin-primary);
            color: white;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .user-image {
            width: 32px;
            height: 32px;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .action-buttons { 
            display: flex; 
            gap: 5px; 
            justify-content: flex-end;
        }
        
        .table {
            font-size: 0.875rem;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            font-weight: 600;
            padding: 0.75rem;
            background-color: var(--admin-secondary);
            color: white;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .table td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        
        .role-select {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            width: auto;
            background-color: white;
        }
        
        .btn-xs {
            padding: 0.125rem 0.375rem;
            font-size: 0.75rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        
        .card {
            border: none;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--admin-primary);
            color: white;
            padding: 1rem;
            border-bottom: none;
            font-weight: 500;
        }
        
        .stats-card {
            background-color: white;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            padding: 1.25rem;
            height: 100%;
            transition: all 0.2s;
            border-left: 4px solid var(--admin-accent);
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .stats-card .icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--admin-accent);
        }
        
        .stats-card .number {
            font-size: 1.75rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--admin-primary);
        }
        
        .stats-card .label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .search-form {
            margin-bottom: 1rem;
        }
        
        .filter-dropdown {
            width: auto;
            border-radius: 0.25rem;
            border: 1px solid #dee2e6;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        /* Custom tooltip */
        .custom-tooltip {
            position: relative;
            display: inline-block;
        }
        
        .custom-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        .tooltip-text {
            visibility: hidden;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 0.25rem;
            padding: 5px 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        
        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        
        .table-responsive {
            overflow-x: auto;
            scrollbar-width: thin;
        }
        
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: #bbb;
            border-radius: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #999;
        }
        
        /* Colorized role badges */
        .role-badge-admin {
            background-color: var(--admin-info);
        }
        
        .role-badge-resident {
            background-color: var(--admin-success);
        }
        
        
        /* Simplified table design */
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .table-hover > tbody > tr:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        /* Stats cards color variations */
        .stats-card.primary { border-left-color: var(--admin-primary); }
        .stats-card.primary .icon { color: var(--admin-primary); }
        
        .stats-card.success { border-left-color: var(--admin-success); }
        .stats-card.success .icon { color: var(--admin-success); }
        
        .stats-card.warning { border-left-color: var(--admin-warning); }
        .stats-card.warning .icon { color: var(--admin-warning); }
        
        .stats-card.info { border-left-color: var(--admin-info); }
        .stats-card.info .icon { color: var(--admin-info); }
        
        .stats-card.secondary { border-left-color: var(--admin-secondary); }
        .stats-card.secondary .icon { color: var(--admin-secondary); }
        
        @media (max-width: 768px) {
            .table td, .table th {
                font-size: 0.75rem;
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="m-0"><i class="fas fa-users-cog me-2"></i>User Management</h2>
                <span class="badge bg-light text-primary fs-6"><?= $result ? $result->num_rows : 0 ?> users</span>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="stats-card primary text-center">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <div class="number"><?= $result ? $result->num_rows : 0 ?></div>
                    <div class="label">Total Users</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 col-sm-6 mb-3">
                <div class="stats-card info text-center">
                    <div class="icon"><i class="fas fa-user-shield"></i></div>
                    <div class="number"><?= $roleStats['admin'] ?></div>
                    <div class="label">Admins</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 col-sm-6 mb-3">
                <div class="stats-card success text-center">
                    <div class="icon"><i class="fas fa-home"></i></div>
                    <div class="number"><?= $roleStats['resident'] ?></div>
                    <div class="label">Residents</div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="row mb-3">
            <div class="col-md-8">
                <div class="search-form">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                        <input type="text" id="userSearch" class="form-control" placeholder="Search by name, email or phone...">
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2 justify-content-md-end">
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

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="m-0"><i class="fas fa-table me-2"></i>Users Directory</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-light me-2" id="refreshTable">
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
                                <th>#</th>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th class="text-center">Status</th>
                                <th>Registered</th>
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

                                                <form method="POST">
                                                    <input type="hidden" name="user_id" value="<?= $user['user_id']; ?>">
                                                    <button type="submit" name="delete_permanent" class="btn btn-xs btn-danger custom-tooltip" 
                                                            onclick="return confirm('Are you sure you want to permanently delete this user? This cannot be undone!')">
                                                        <i class="fas fa-trash"></i>
                                                        <span class="tooltip-text">Delete</span>
                                                    </button>
                                                </form>
                                                
                                                <a href="#" class="btn btn-xs btn-info text-white custom-tooltip view-user" data-user-id="<?= $user['user_id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                    <span class="tooltip-text">View Details</span>
                                                </a>
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
                    <span class="text-muted">Showing <span id="showing-count"><?= $result ? $result->num_rows : 0 ?></span> users</span>
                </div>
                <div>
                    <span class="badge bg-success me-2"><?= $statusStats['active'] ?> Active</span>
                    <span class="badge bg-danger"><?= $statusStats['inactive'] ?> Inactive</span>
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
            
            // Search functionality
            const userSearch = document.getElementById('userSearch');
            const userRows = document.querySelectorAll('.user-row');
            
            userSearch.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                
                userRows.forEach(row => {
                    const userCell = row.children[1].textContent.toLowerCase();
                    const contactCell = row.children[2].textContent.toLowerCase();
                    
                    if (userCell.includes(searchTerm) || contactCell.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                updateShownCount();
            });
            
            // Role filter
            const roleFilter = document.getElementById('roleFilter');
            roleFilter.addEventListener('change', applyFilters);
            
            // Status filter
            const statusFilter = document.getElementById('statusFilter');
            statusFilter.addEventListener('change', applyFilters);
            
            function applyFilters() {
                const roleValue = roleFilter.value;
                const statusValue = statusFilter.value;
                
                userRows.forEach(row => {
                    const rowRole = row.getAttribute('data-role');
                    const rowStatus = row.getAttribute('data-status');
                    
                    const roleMatch = roleValue === 'all' || rowRole === roleValue;
                    const statusMatch = statusValue === 'all' || rowStatus === statusValue;
                    
                    if (roleMatch && statusMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                updateShownCount();
            }
            
            function updateShownCount() {
                const visibleRows = document.querySelectorAll('.user-row[style=""]').length + 
                                   document.querySelectorAll('.user-row:not([style])').length;
                document.getElementById('showing-count').textContent = visibleRows;
            }
            
            // Refresh button
            document.getElementById('refreshTable').addEventListener('click', function() {
                window.location.reload();
            });
            
            // View user details (placeholder - would link to detailed view)
            document.querySelectorAll('.view-user').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const userId = this.getAttribute('data-user-id');
                    alert('View details for user ID: ' + userId + '\nThis would open a detailed view.');
                });
            });
            
    </script>
</body>
</html>

<?php $conn->close(); ?>