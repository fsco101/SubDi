<?php
ob_start(); // Start output buffering
include '../includes/header.php';

// Ensure only admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied. Only administrators can manage announcements.");
}

// Delete announcement if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $announcement_id = $_GET['delete'];

    // Check if there's an image to delete
    $query = "SELECT image_url FROM announcements WHERE announcement_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $announcement_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (!empty($row['image_url'])) {
            $image_path = $_SERVER['DOCUMENT_ROOT'] . $row['image_url'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
    }
    $stmt->close(); // Close previous statement

    // Delete the announcement from the database
    $query = "DELETE FROM announcements WHERE announcement_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $announcement_id);
    
    if ($stmt->execute()) {
        $success_message = "Announcement deleted successfully.";
    } else {
        $error_message = "Error deleting announcement.";
    }
    $stmt->close();
}

// Fetch all announcements for display
$query = "SELECT a.announcement_id, a.title, a.content, a.created_at, a.image_url, u.user_id, u.email, u.f_name, u.l_name
          FROM announcements a 
          LEFT JOIN users u ON a.posted_by = u.user_id 
          ORDER BY a.created_at DESC";
$result = $conn->query($query);

ob_end_flush(); // End output buffering
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6fff;
            --primary-dark: #3451c7;
            --secondary-color: #f0f4ff;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --border-color: #e0e0e0;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: #f8fafd;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 12000px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .admin-panel {
            background-color: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .panel-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .panel-title i {
            margin-right: 10px;
        }
        
        .admin-alert {
            background-color: var(--secondary-color);
            border-left: 4px solid var(--primary-color);
            padding: 15px 20px;
            margin-bottom: 20px;
            font-size: 15px;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }
        
        .admin-alert i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background-color: white;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-title {
            color: var(--text-color);
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }
        
        .button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .button i {
            margin-right: 8px;
        }
        
        .button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .button-delete {
            background-color: var(--error-color);
        }
        
        .button-delete:hover {
            background-color: #c0392b;
        }
        
        .button-edit {
            background-color: #f39c12;
        }
        
        .button-edit:hover {
            background-color: #d35400;
        }
        
        .status-message {
            padding: 15px 20px;
            border-radius: 6px;
            margin: 20px 30px;
            font-size: 15px;
            display: flex;
            align-items: center;
        }
        
        .status-message i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .success-message {
            color: #155724;
            background-color: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .table-container {
            padding: 20px 30px;
            overflow-x: auto;
            width: 100%;
            
        }
        
        .announcements-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }
        
        .announcements-table th,
        .announcements-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            color:black;
        }
        
        .announcements-table th {
            background-color: #f8f9fb;
            color: #555;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
        }
        
        .announcements-table tr:last-child td {
            border-bottom: none;
        }
        
        .announcements-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .title-column {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }
        
        .content-column {
            max-width: 400px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #666;
        }
        
        .thumbnail {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        
        .no-image {
            width: 80px;
            height: 60px;
            background-color: #f1f1f1;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #777;
        }
        
        .date-column {
            white-space: nowrap;
            color: #666;
        }
        
        .email-column {
            white-space: nowrap;
            color: #666;
        }
        
        .empty-state {
            padding: 50px 0;
            text-align: center;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            margin: 10px 0;
        }
        
        .confirmation-modal {
            display: none; 
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .modal-icon {
            font-size: 48px;
            color: var(--error-color);
            margin-bottom: 20px;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
        }
        
        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .button {
                width: 100%;
                justify-content: center;
            }
            
            .announcements-table th:nth-child(3),
            .announcements-table td:nth-child(3) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-alert">
            <i class="fas fa-shield-alt"></i>
            <span><strong>Admin Access</strong> - You are logged in as an administrator with announcement management privileges.</span>
        </div>
        
        <div class="admin-panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="fas fa-bullhorn"></i> Announcement Management</h2>
            </div>
            
            <div class="header-actions">
                <h3 class="section-title">All Announcements</h3>
                <a href="create_announcement.php" class="button"><i class="fas fa-plus"></i> Create New Announcement</a>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="status-message success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="status-message error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>
            
            <div class="table-container">
                <?php if ($result->num_rows > 0): ?>
                    <table class="announcements-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Content</th>
                                <th>Posted By</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($row['image_url'])): ?>
                                            <img src="<?php echo $row['image_url']; ?>" alt="Announcement Image" class="thumbnail">
                                        <?php else: ?>
                                            <div class="no-image"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="title-column"><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td class="content-column"><?php echo htmlspecialchars(substr($row['content'], 0, 100)) . (strlen($row['content']) > 100 ? '...' : ''); ?></td>
                                    <td class="email-column"><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td class="date-column"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td class="actions">
                                        <a href="edit_announcement.php?id=<?php echo $row['announcement_id']; ?>" class="button button-edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button class="button button-delete" onclick="confirmDelete(<?php echo $row['announcement_id']; ?>, '<?php echo addslashes(htmlspecialchars($row['title'])); ?>')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h3>No Announcements Found</h3>
                        <p>There are no announcements to display.</p>
                        <a href="create_announcement.php" class="button"><i class="fas fa-plus"></i> Create Your First Announcement</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="confirmation-modal">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h3>Confirm Deletion</h3>
            <p id="deleteMessage">Are you sure you want to delete this announcement?</p>
            <div class="modal-buttons">
                <button class="button" onclick="closeModal()"><i class="fas fa-times"></i> Cancel</button>
                <a id="confirmDeleteButton" href="#" class="button button-delete"><i class="fas fa-trash-alt"></i> Delete</a>
            </div>
        </div>
    </div>
    
    <script>
        // Delete confirmation modal
        function confirmDelete(id, title) {
            const modal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmButton = document.getElementById('confirmDeleteButton');
            
            deleteMessage.textContent = `Are you sure you want to delete the announcement "${title}"?`;
            confirmButton.href = `index_announcement.php?delete=${id}`;
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'none';
        }
        
        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
        
        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            const successMessage = document.querySelector('.success-message');
            if (successMessage) {
                successMessage.style.opacity = '0';
                successMessage.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    if (successMessage) {
                        successMessage.style.display = 'none';
                    }
                }, 500);
            }
        }, 5000);
    </script>
</body>
</html>
<?php include '../includes/footer.php'; ?>