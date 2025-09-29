<?php
include '../includes/header.php';
include '../send_email.php';

// Enable error logging
ini_set('display_errors', 0); // Don't display errors to users
error_log("Approve Rentals script started");

// Check for POST request and authenticate user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate that we have the required parameters
    if (!isset($_POST['rental_id']) || !isset($_POST['action'])) {
        echo "Missing required parameters";
        exit();
    }
    
    // Check that we have an active session with a user ID
    if (!isset($_SESSION['user_id'])) {
        echo "Authentication required";
        exit();
    }
    
    $rentalId = intval($_POST['rental_id']);
    $action = $_POST['action'];
    $adminId = $_SESSION['user_id'];
    
    // Validate action
    if ($action !== 'approve' && $action !== 'reject') {
        echo "Invalid action parameter";
        exit();
    }
    
    // Set status based on action
    if ($action === 'approve') {
        $status = 'approved';
        $paymentStatus = 'paid';
    } else {
        $status = 'rejected';
        $paymentStatus = 'pending';
    }

    // Start transaction for consistent updates
    $conn->begin_transaction();

    try {
        // Update rental status without rejection reason
        $stmt = $conn->prepare("UPDATE rentals SET status = ?, payment_status = ? WHERE rental_id = ?");
        $stmt->bind_param("ssi", $status, $paymentStatus, $rentalId);
        error_log("Executing query for rental $rentalId");
        $stmt->execute();

        // Fetch user email and item details
        $userResult = $conn->query("SELECT u.email, r.item_id, r.user_id FROM rentals r JOIN users u ON r.user_id = u.user_id WHERE r.rental_id = $rentalId");
        $user = $userResult->fetch_assoc();
        $to = $user['email'];
        $itemId = $user['item_id'];
        $userId = $user['user_id'];

        // Fetch item name
        $itemResult = $conn->query("SELECT name FROM rental_items WHERE item_id = $itemId");
        $item = $itemResult->fetch_assoc();
        $itemName = $item['name'];

        // Log admin action
        $actionText = $action === 'approve' ? "Approved rental #$rentalId" : "Rejected rental #$rentalId";
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)");
        $stmt->bind_param("is", $adminId, $actionText);
        $stmt->execute();

        // Create notification
        $message = $action === 'approve' 
            ? "Your rental request for <strong>$itemName</strong> has been approved and marked as paid."
            : "Your rental request for <strong>$itemName</strong> has been rejected.";

        // Add receipt link if approved
        if ($action === 'approve') {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $receiptUrl = $protocol . $_SERVER['HTTP_HOST'] . "/subdisystem/rentals/receipt.php?rental_id=$rentalId";
            $message .= " <a href='$receiptUrl'>View Receipt</a>";
        }

        $notifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message, created_at) 
                      VALUES (?, ?, 'rental', ?, NOW())";
        $stmt = $conn->prepare($notifQuery);
        $stmt->bind_param("iis", $userId, $rentalId, $message);
        $stmt->execute();

        // Send email notification
        $subject = $action === 'approve' ? "Rental Approved: $itemName" : "Rental Rejected: $itemName";
        sendEmail($to, $subject, $message);

        // Send receipt copy to admin if approved
        if ($action === 'approve') {
            $adminEmail = "subdisystem@gmail.com"; // Make sure to replace with actual admin email
            sendEmail($adminEmail, "Rental Receipt Copy for Rental #$rentalId", $message);
        }

        // Commit transaction
        $conn->commit();
        echo "Transaction succeeded";
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error: " . $e->getMessage());
        echo "Transaction failed: " . $e->getMessage();
    }

    $stmt->close();
    exit();
}

// Fetch rentals waiting for approval
$query = "SELECT r.rental_id, r.user_id, u.f_name, u.l_name, u.email, u.phone_number, 
           u.role, u.image_url, i.name AS item_name, i.description, 
           i.rental_price, i.image_path, r.rental_start, r.rental_end, 
           r.quantity, r.status, r.payment_status, r.total_payment 
    FROM rentals r 
    JOIN users u ON r.user_id = u.user_id 
    JOIN rental_items i ON r.item_id = i.item_id 
    WHERE r.status = 'pending' 
    ORDER BY r.rental_start ASC";

$result = $conn->query($query);

// Check for query error
if ($result === false) {
    error_log("Error in rentals query: " . $conn->error);
    $rentals = [];
} else {
    $rentals = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin - Approve Rentals</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/subdisystem/style/style.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <h2><i class="bi bi-box-seam"></i> Rental Approval Dashboard</h2>
            <div class="d-flex align-items-center">
                <button id="approve-all-btn" class="btn btn-success me-3">
                    <i class="bi bi-check-all"></i> Approve All
                </button>
                <button id="toggle-view-btn" class="view-toggle-btn" data-current-view="grid">
                    <i class="bi bi-table"></i> Switch to Table View
                </button>
                <span class="rental-count ms-3"><?= count($rentals) ?> Pending Rentals</span>
            </div>
        </div>
        
        <div id="notification" class="notification"></div>
        
        <?php if (empty($rentals)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>No Pending Rentals</h3>
                <p>There are currently no rental requests waiting for approval.</p>
            </div>
        <?php else: ?>
            <!-- Grid View (Default) -->
            <div id="grid-view" class="rental-grid">
                <?php foreach ($rentals as $rental): ?>
                    <div id="grid-item-<?= $rental['rental_id']; ?>" class="card rental-card">
                        <div class="card-body">
                            <div class="user-info">
                                <img class="user-avatar" src="<?= htmlspecialchars($rental['image_url'] ?: '/subdisystem/images/default_avatar.png'); ?>" alt="User">
                                <div>
                                    <strong><?= htmlspecialchars($rental['f_name'] . ' ' . $rental['l_name']); ?></strong>
                                    <div class="text-secondary"><?= htmlspecialchars($rental['email']); ?></div>
                                </div>
                            </div>
                            
                            <img class="item-image" src="<?= htmlspecialchars('/subdisystem/rentals/item_upload/' . basename($rental['image_path'])); ?>" alt="<?= htmlspecialchars($rental['item_name']); ?>">
                            <div class="item-details">
                                <div class="price-tag">
                                    <i class="bi bi-tag-fill"></i> ₱<?= htmlspecialchars(number_format($rental['rental_price'], 2)); ?> per item
                                </div>
                                <div class="item-name"><?= htmlspecialchars($rental['item_name']); ?></div>
                                <div class="item-description"><?= htmlspecialchars($rental['description']); ?></div>
                                <div class="total-payment">
                                    <i class="bi bi-cash-stack"></i> Total: ₱<?= number_format($rental['total_payment'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="rental-dates">
                                <div><i class="bi bi-calendar-check"></i> <strong>Start:</strong> <?= date('M j, Y', strtotime($rental['rental_start'])); ?></div>
                                <div><i class="bi bi-calendar-x"></i> <strong>End:</strong> <?= date('M j, Y', strtotime($rental['rental_end'])); ?></div>
                            </div>
                            <div class="rental-details">
                                <div class="rental-detail">
                                    <i class="bi bi-123"></i> Quantity: <strong><?= htmlspecialchars($rental['quantity']); ?></strong>
                                </div>
                                <div class="rental-detail">
                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($rental['phone_number']); ?>
                                </div>
                                <div class="rental-detail">
                                    <i class="bi bi-hash"></i> ID: <?= htmlspecialchars($rental['rental_id']); ?>
                                </div>
                                <div class="rental-detail">
                                    <i class="bi bi-person-badge"></i> <?= ucfirst(htmlspecialchars($rental['role'])); ?>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <span id="status-<?= $rental['rental_id']; ?>" class="status-badge status-<?= $rental['status']; ?>">
                                    <?= ucfirst(htmlspecialchars($rental['status'])); ?>
                                </span>
                                <span id="payment-<?= $rental['rental_id']; ?>" class="payment-badge payment-<?= $rental['payment_status']; ?>">
                                    <?= ucfirst(htmlspecialchars($rental['payment_status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="action-buttons">
                                <button class="btn btn-approve" onclick="updateRental(<?= $rental['rental_id']; ?>, 'approve')">
                                    <i class="bi bi-check-circle"></i> Approve
                                </button>
                                <button class="btn btn-reject" onclick="updateRental(<?= $rental['rental_id']; ?>, 'reject')">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Table View (Initially Hidden) -->
            <div id="table-view" class="hidden">
                <div class="card">
                    <div class="table-responsive">
                        <table class="rental-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Rental Period</th>
                                    <th>Status</th>
                                    <th>Total Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rentals as $rental): ?>
                                    <tr id="table-row-<?= $rental['rental_id']; ?>">
                                        <td><?= htmlspecialchars($rental['rental_id']); ?></td>
                                        <td>
                                            <div class="user-info mb-0">
                                                <img class="user-avatar" src="<?= htmlspecialchars($rental['image_url'] ?: '/subdisystem/images/default_avatar.png'); ?>" alt="User" style="width:30px;height:30px;">
                                                <div>
                                                    <?= htmlspecialchars($rental['f_name'] . ' ' . $rental['l_name']); ?><br>
                                                    <small><?= htmlspecialchars($rental['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= htmlspecialchars('/subdisystem/rentals/item_upload/' . basename($rental['image_path'])); ?>" 
                                                    alt="Item" width="40" height="40" style="border-radius:6px;margin-right:10px;">
                                                <?= htmlspecialchars($rental['item_name']); ?>
                                            </div>
                                        </td>
                                        <td><strong><?= htmlspecialchars($rental['quantity']); ?></strong></td>
                                        <td>
                                            <?= date('M j, Y', strtotime($rental['rental_start'])); ?> - 
                                            <?= date('M j, Y', strtotime($rental['rental_end'])); ?>
                                        </td>
                                        <td>
                                            <span id="table-status-<?= $rental['rental_id']; ?>" class="status-badge status-<?= $rental['status']; ?>">
                                                <?= ucfirst(htmlspecialchars($rental['status'])); ?>
                                            </span>
                                        </td>
                                        <td>₱<?= number_format($rental['total_payment'], 2); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-approve btn-sm" onclick="updateRental(<?= $rental['rental_id']; ?>, 'approve')">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <button class="btn btn-reject btn-sm" onclick="updateRental(<?= $rental['rental_id']; ?>, 'reject')">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle view between grid and table
        const toggleViewBtn = document.getElementById('toggle-view-btn');
        const gridView = document.getElementById('grid-view');
        const tableView = document.getElementById('table-view');
        
        if (toggleViewBtn) {
            toggleViewBtn.addEventListener('click', function() {
                const currentView = this.getAttribute('data-current-view');
                
                if (currentView === 'grid') {
                    gridView.classList.add('hidden');
                    tableView.classList.remove('hidden');
                    this.setAttribute('data-current-view', 'table');
                    this.innerHTML = '<i class="bi bi-grid"></i> Switch to Grid View';
                } else {
                    gridView.classList.remove('hidden');
                    tableView.classList.add('hidden');
                    this.setAttribute('data-current-view', 'grid');
                    this.innerHTML = '<i class="bi bi-table"></i> Switch to Table View';
                }
            });
        }

        // Simplify updateRental function
        function updateRental(rentalId, action) {
            if (confirm(`Are you sure you want to ${action} this rental request?`)) {
                const formData = new FormData();
                formData.append('rental_id', rentalId);
                formData.append('action', action);

                fetch(window.location.href, {
                    method: "POST",
                    body: formData
                })
                .then(response => response.text())
                .then(text => {
                    if (text.includes("Transaction succeeded")) {
                        updateUIAfterAction(rentalId, action);
                        showNotification(`Rental #${rentalId} has been ${action}d.`, 'success');
                    } else {
                        showNotification("Error: " + text, 'error');
                    }
                })
                .catch(error => {
                    console.error("Fetch Error:", error);
                    showNotification("Error: " + error.message, 'error');
                });
            }
        }

        // Function to update UI after successful action
        function updateUIAfterAction(rentalId, action) {
            // Update status and payment badges
            if (document.getElementById(`status-${rentalId}`)) {
                document.getElementById(`status-${rentalId}`).innerHTML = action === 'approve' ? 'Approved' : 'Rejected';
                document.getElementById(`status-${rentalId}`).className = `status-badge status-${action === 'approve' ? 'approved' : 'rejected'}`;
            }
            
            if (document.getElementById(`table-status-${rentalId}`)) {
                document.getElementById(`table-status-${rentalId}`).innerHTML = action === 'approve' ? 'Approved' : 'Rejected';
                document.getElementById(`table-status-${rentalId}`).className = `status-badge status-${action === 'approve' ? 'approved' : 'rejected'}`;
            }
            
            if (document.getElementById(`payment-${rentalId}`)) {
                document.getElementById(`payment-${rentalId}`).innerHTML = action === 'approve' ? 'Paid' : 'Pending';
                document.getElementById(`payment-${rentalId}`).className = `payment-badge payment-${action === 'approve' ? 'paid' : 'pending'}`;
            }
            
            // Fade out and remove the items after a short delay
            setTimeout(() => {
                const gridItem = document.getElementById(`grid-item-${rentalId}`);
                const tableRow = document.getElementById(`table-row-${rentalId}`);
                
                if (gridItem) {
                    gridItem.style.opacity = '0.5';
                    gridItem.style.transition = 'opacity 0.5s, transform 0.5s';
                    gridItem.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        if (gridItem.parentNode) {
                            gridItem.remove();
                        }
                    }, 500);
                }   
                
                if (tableRow) {
                    tableRow.style.opacity = '0.5';
                    tableRow.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        if (tableRow.parentNode) {
                            tableRow.remove();
                        }
                    }, 500);
                }
                
                // Update rental count
                updateRentalCount();
            }, 1000);
        }

        // Improved notification function
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            if (!notification) return;
            
            // Clear any existing notifications
            clearTimeout(window.notificationTimeout);
            
            // Set icon based on notification type
            let icon;
            switch(type) {
                case 'success':
                    icon = '<i class="bi bi-check-circle-fill"></i>';
                    break;
                case 'error':
                    icon = '<i class="bi bi-exclamation-triangle-fill"></i>';
                    break;
                case 'info':
                    icon = '<i class="bi bi-info-circle-fill"></i>';
                    break;
                default:
                    icon = '<i class="bi bi-bell-fill"></i>';
            }
            
            // Update notification content and style
            notification.innerHTML = `${icon} ${message}`;
            
            // Reset all classes first
            notification.className = 'notification';
            
            // Force a browser reflow to ensure animation works
            void notification.offsetWidth;
            
            // Add the appropriate class
            notification.classList.add(type);
            notification.classList.add('show');
            
            // Auto-hide notification after delay
            const delay = type === 'error' ? 5000 : 3000;
            window.notificationTimeout = setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.classList.remove('success', 'error', 'info');
                }, 300);
            }, delay);
        }

        // Update rental count and check if all are processed
        function updateRentalCount() {
            const rentalCount = document.querySelector('.rental-count');
            if (rentalCount) {
                const countText = rentalCount.textContent;
                const countMatch = countText.match(/(\d+)/);
                
                if (countMatch && countMatch[1]) {
                    const count = parseInt(countMatch[1]);
                    const newCount = Math.max(0, count - 1); // Ensure count doesn't go below 0
                    rentalCount.textContent = `${newCount} Pending Rentals`;
                    
                    // Show empty state if no more rentals
                    if (newCount === 0) {
                        handleEmptyState();
                    }
                }
            }
        }

        // Function to handle empty state display
        function handleEmptyState() {
            const container = document.querySelector('.container');
            const dashboardHeader = document.querySelector('.dashboard-header');
            const gridView = document.getElementById('grid-view');
            const tableView = document.getElementById('table-view');
            
            // Check if empty state already exists
            if (!document.querySelector('.empty-state')) {
                // Remove grid and table views if they exist
                if (gridView) gridView.remove();
                if (tableView) tableView.remove();
                
                // Create empty state element
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.innerHTML = `
                    <i class="bi bi-inbox"></i>
                    <h3>No Pending Rentals</h3>
                    <p>There are currently no rental requests waiting for approval.</p>
                    <button class="btn btn-primary mt-3" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Page
                    </button>
                `;
                
                // Insert after dashboard header
                if (dashboardHeader) {
                    dashboardHeader.insertAdjacentElement('afterend', emptyState);
                } else if (container) {
                    container.appendChild(emptyState);
                }
                
                // Show notification
                showNotification("All rentals processed. You can now refresh the page.", 'success');
            }
        }

        // Approve all rentals function
        document.getElementById('approve-all-btn').addEventListener('click', function() {
            if (confirm("Are you sure you want to approve all pending rentals? This will mark all payments as paid.")) {
                const rentalIds = <?= json_encode(array_column($rentals, 'rental_id')); ?>;
                const promises = rentalIds.map(rentalId => {
                    return fetch(window.location.href, {
                        method: "POST",
                        headers: { 
                            "Content-Type": "application/x-www-form-urlencoded",
                            "X-Requested-With": "XMLHttpRequest"
                        },
                        body: `rental_id=${rentalId}&action=approve`
                    }).then(response => response.text());
                });

                Promise.all(promises).then(results => {
                    let allSucceeded = true;
                    results.forEach((result, index) => {
                        if (!result.includes("Transaction succeeded")) {
                            allSucceeded = false;
                            showNotification(`Failed to approve rental #${rentalIds[index]}: ${result}`, 'error');
                        } else {
                            updateUIAfterAction(rentalIds[index], 'approve');
                        }
                    });

                    if (allSucceeded) {
                        showNotification("All pending rentals have been approved and marked as paid.", 'success');
                    }
                }).catch(error => {
                    console.error("Fetch Error:", error);
                    showNotification("Error: " + error.message, 'error');
                });
            }
        });
    </script>
</body>
</html>

<style>
            :root {
            --primary: #4285f4;
            --primary-dark: #3367d6;
            --success: #34a853;
            --warning: #fbbc05;
            --danger: #ea4335;
            --light: #f8f9fa;
            --dark: #202124;
            --border: #e0e0e0;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }
        
        .dashboard-header {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var (--shadow);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dashboard-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            padding-top: 5%;
        }
        
        .rental-count {
            background-color: var (--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var (--shadow);
            overflow: hidden;
            border: 1px solid var (--border);
            margin-bottom: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .rental-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .rental-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .rental-card .card-body {
            flex: 1;
            padding: 20px;
        }
        
        .rental-card .card-footer {
            background-color: #f8f9fa;
            padding: 15px;
            border-top: 1px solid var (--border);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var (--primary);
        }
        
        .item-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: transform 0.3s;
        }
        
        .item-image:hover {
            transform: scale(1.02);
        }
        
        .item-details {
            margin-bottom: 15px;
        }
        
        .item-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .item-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .rental-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .rental-detail {
            background-color: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-bottom: 8px;
            flex-basis: 48%;
            display: flex;
            align-items: center;
            gap: 5px;
            border: 1px solid var (--border);
        }
        
        .rental-dates {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            border: 1px solid var (--border);
        }
        
        .rental-dates i {
            color: var (--primary);
            margin-right: 5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            margin-right: 8px;
        }
        
        .status-pending {
            background-color: var (--warning);
            color: #333;
        }
        
        .status-approved {
            background-color: var (--success);
            color: white;
        }
        
        .status-rejected {
            background-color: var (--danger);
            color: white;
        }
        
        .payment-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .payment-paid {
            background-color: rgba(52, 168, 83, 0.2);
            color: #34a853;
        }
        
        .payment-pending {
            background-color: rgba(251, 188, 5, 0.2);
            color: #d39e00;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .btn-approve {
            background-color: var (--success);
            color: green;
            flex: 1;
        }
        
        .btn-approve:hover {
            background-color: #2d9448;
            transform: translateY(-2px);
        }
        
        .btn-reject {
            background-color: var (--danger);
            color: red;
            flex: 1;
        }
        
        .btn-reject:hover {
            background-color: #d23c2f;
            transform: translateY(-2px);
        }
        
        .price-tag {
            background-color: var (--primary);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 10px;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .total-payment {
            font-weight: bold;
            color: var (--primary);
            font-size: 1.1rem;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        
        .empty-state {
            padding: 60px 40px;
            text-align: center;
            background-color: white;
            border-radius: 10px;
            box-shadow: var (--shadow);
            margin-top: 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #aaa;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #666;
            max-width: 500px;
            margin: 0 auto;
        }
        
               /* Enhanced Notification Styles */
               .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 9999;
                opacity: 0;
                transform: translateY(-20px);
                transition: opacity 0.3s, transform 0.3s;
                max-width: 350px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            
            .notification.show {
                opacity: 1;
                transform: translateY(0);
            }
            
            .notification.success {
                background-color: #28a745;
            }
            
            .notification.error {
                background-color: #dc3545;
            }
            
            .notification.info {
                background-color: #17a2b8;
            }
            
            .notification i {
                margin-right: 8px;
            }
            
            @media (max-width: 768px) {
                .notification {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
            }
        
        .view-toggle-btn {
            background-color: var (--primary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-toggle-btn:hover {
            background-color: var (--primary-dark);
            transform: translateY(-2px);
        }
        
        /* Table view styling */
        .rental-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var (--shadow);
        }
        
        .rental-table th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid var (--border);
            color: #555;
            font-size: 0.9rem;
        }
        
        .rental-table tr {
            transition: background-color 0.2s;
        }
        
        .rental-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .rental-table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid var (--border);
            font-size: 0.95rem;
            color:black;
        }
        
        .hidden {
            display: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .rental-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                
            }
            
            .dashboard-header > div {
                width: 100%;
                display: flex;
                justify-content: space-between;
            }
            
            .rental-table {
                display: block;
                overflow-x: auto;
            }
        }
</style>
