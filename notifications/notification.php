<?php
ob_start();
include '../includes/header.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: /subdisystem/user/login.php");
    exit();
}

// Handle AJAX requests for actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    // Delete single notification
    if ($_POST['action'] === 'delete_notification' && isset($_POST['notification_id'])) {
        $notif_id = $_POST['notification_id'];
        $deleteQuery = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("ii", $notif_id, $user_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Notification deleted successfully.';
        } else {
            $response['message'] = 'Failed to delete notification.';
        }
    }
    
    // Delete all notifications
    if ($_POST['action'] === 'delete_all') {
        $deleteAllQuery = "DELETE FROM notifications WHERE user_id = ?";
        $stmt = $conn->prepare($deleteAllQuery);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'All notifications cleared successfully.';
        } else {
            $response['message'] = 'Failed to clear notifications.';
        }
    }
    
    // Mark all as read
    if ($_POST['action'] === 'mark_all_read') {
        $markReadQuery = "UPDATE notifications SET is_read = TRUE WHERE user_id = ?";
        $stmt = $conn->prepare($markReadQuery);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'All notifications marked as read.';
        } else {
            $response['message'] = 'Failed to mark notifications as read.';
        }
    }
    
    // Send JSON response for AJAX requests
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Mark selected notifications as read when the page loads
if (isset($_GET['view']) && $_GET['view'] === 'unread') {
    // Only fetch unread, but don't mark as read
    $query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC";
} else {
    // Mark all as read when viewing all notifications
    $updateQuery = "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Fetch all notifications
    $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
}

// Execute the query to fetch notifications
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count unread notifications
$countQuery = "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unreadCount = $stmt->get_result()->fetch_assoc()['unread_count'];

// Pagination setup
$itemsPerPage = 10;
$totalItems = count($notifications);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;
$currentPageItems = array_slice($notifications, $offset, $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Notifications</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .notification-unread {
            border-left-color: #1a73e8;
            background-color: rgba(26, 115, 232, 0.05);
        }
                
        .notification-actions {
            background: none;
            border: none;
            box-shadow: none;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 14px;
            color: #d9534f;
            transition: color 0.3s ease;
        }

        .notification-actions:hover {
            color: #b52b27;
            text-decoration: underline;
        }

        .notification-actions-container {
            display: flex;
            align-items: center;
            justify-content: start;
            gap: 10px;
        }
        
        .badge-counter {
            background-color: #1a73e8;
            color: white;
            font-size: 0.7rem;
            padding: 0.25em 0.6em;
            border-radius: 10px;
        }
        
        .notification-message {
            margin-right: 100px;
            word-break: break-word;
        }
        
        .alert-feedback {
            animation: fadeOut 5s forwards;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 350px;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        .btn-action {
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Toast for feedback messages -->
        <div id="notification-toast" class="toast align-items-center text-white bg-success border-0 alert-feedback" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toast-message">
                    Action completed successfully.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h2 class="h4 mb-0">Notifications</h2>
                <div class="btn-group">
                    <a href="notification.php" class="btn btn-sm <?= !isset($_GET['view']) ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
                    <a href="notification.php?view=unread" class="btn btn-sm <?= isset($_GET['view']) && $_GET['view'] === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Unread
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if (empty($currentPageItems)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="mt-3 text-muted">No notifications available.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush" id="notification-list">
                        <?php foreach ($currentPageItems as $notif): ?>
                            <div class="list-group-item notification-item <?= $notif['is_read'] ? '' : 'notification-unread' ?>" id="notification-<?= $notif['notification_id'] ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="notification-message">
                                        <?= htmlspecialchars($notif['message']); ?>
                                    </div>
                                    <div class="notification-actions">
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-notification" 
                                               data-notification-id="<?= $notif['notification_id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="notification-time mt-1">
                                    <?php 
                                        $date = new DateTime($notif['created_at']);
                                        echo $date->format('M j, Y - g:i A'); 
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($notifications)): ?>
                <div class="card-footer bg-white d-flex justify-content-between">
                    <div>
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Notification pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= isset($_GET['view']) ? 'view=' . $_GET['view'] . '&' : '' ?>page=<?= $currentPage - 1 ?>">Previous</a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= ($i === $currentPage) ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= isset($_GET['view']) ? 'view=' . $_GET['view'] . '&' : '' ?>page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= isset($_GET['view']) ? 'view=' . $_GET['view'] . '&' : '' ?>page=<?= $currentPage + 1 ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <button type="button" id="mark-all-read" class="btn btn-sm btn-outline-primary">Mark All as Read</button>
                        <button type="button" id="delete-all" class="btn btn-sm btn-outline-danger ms-2">Clear All</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Helper function to show toast messages
        function showToast(message, type = 'success') {
            const toast = document.getElementById('notification-toast');
            const toastMessage = document.getElementById('toast-message');
            
            // Set message and color based on type
            toastMessage.textContent = message;
            toast.classList.remove('bg-success', 'bg-danger');
            toast.classList.add(type === 'success' ? 'bg-success' : 'bg-danger');
            
            // Show toast
            toast.style.display = 'block';
            
            // Hide after 5 seconds
            setTimeout(() => {
                toast.style.display = 'none';
            }, 5000);
        }
        
        // Helper function to perform AJAX requests
        function performAction(action, data = {}) {
            // Combine the action with any additional data
            const formData = new FormData();
            formData.append('action', action);
            
            // Add any additional data
            for (const key in data) {
                formData.append(key, data[key]);
            }
            
            // Send the request
            return fetch('notification.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    return true;
                } else {
                    showToast(data.message || 'Action failed', 'danger');
                    return false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'danger');
                return false;
            });
        }
        
        // Delete a single notification
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-notification')) {
                const button = e.target.closest('.delete-notification');
                const notificationId = button.dataset.notificationId;
                
                if (confirm('Delete this notification?')) {
                    performAction('delete_notification', { notification_id: notificationId })
                        .then(success => {
                            if (success) {
                                // Remove the notification from the DOM
                                const notificationElement = document.getElementById(`notification-${notificationId}`);
                                if (notificationElement) {
                                    notificationElement.remove();
                                }
                                
                                // Check if there are no more notifications
                                const notificationList = document.getElementById('notification-list');
                                if (notificationList && notificationList.children.length === 0) {
                                    location.reload(); // Reload to show empty state
                                }
                                
                                updateNotificationBadge();
                            }
                        });
                }
            }
        });
        
        // Mark all as read
        document.getElementById('mark-all-read').addEventListener('click', function() {
            performAction('mark_all_read')
                .then(success => {
                    if (success) {
                        // Update UI to show all notifications as read
                        document.querySelectorAll('.notification-unread').forEach(item => {
                            item.classList.remove('notification-unread');
                        });
                        
                        // Update unread badge to 0
                        const unreadBadge = document.querySelector('.badge.bg-danger');
                        if (unreadBadge) {
                            unreadBadge.style.display = 'none';
                        }
                        
                        updateNotificationBadge();
                    }
                });
        });
        
        // Delete all notifications
        document.getElementById('delete-all').addEventListener('click', function() {
            if (confirm('Delete all notifications? This cannot be undone.')) {
                performAction('delete_all')
                    .then(success => {
                        if (success) {
                            // Reload the page to show empty state
                            location.reload();
                        }
                    });
            }
        });
        
        // Update notification badge in header
        function updateNotificationBadge() {
            fetch('/subdisystem/notifications/check_unread.php')
                .then(response => response.json())
                .then(data => {
                    let notifBadge = document.getElementById('notif-badge');
                    if (notifBadge) {
                        notifBadge.style.display = data.unread > 0 ? 'block' : 'none';
                        notifBadge.innerHTML = data.unread > 9 ? '9+' : data.unread;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Initialize on page load
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(updateNotificationBadge, 1000);
        });
    </script>
</body>
</html>