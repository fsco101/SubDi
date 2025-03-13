<?php
ob_start(); // Start output buffering
include '../includes/header.php';

// Ensure only admins can access
if ($_SESSION['role'] !== 'admin') {
    die("Access Denied. Only administrators can post announcements.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $admin_id = $_SESSION['user_id'];
    
    // Get notification settings
    $send_notification = isset($_POST['send_notification']) ? 1 : 0;
    $notification_text = isset($_POST['notification_text']) ? $_POST['notification_text'] : "";
    
    // If notification is enabled but no text, use the title as default
    if ($send_notification && empty($notification_text)) {
        $notification_text = "New announcement: " . $title;
    }

    // Handle Image Upload
    $image_url = null;
    if (!empty($_FILES["image"]["name"])) {
        // Set correct absolute path for image upload
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . "/subdisystem/announcements/uploads/";

        // Ensure the directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Generate a unique filename
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        // Allowed file types
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($fileType), $allowedTypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $image_url = "/subdisystem/announcements/uploads/" . $fileName; // Save relative URL
            } else {
                $error = "Error uploading image.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF allowed.";
        }
    }

    // Insert into the database
    $query = "INSERT INTO announcements (title, content, posted_by, image_url) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssis", $title, $content, $admin_id, $image_url);

    if ($stmt->execute()) {
        $announcement_id = $conn->insert_id; // Get the ID of the new announcement
        
        // Log the admin action
        $actionQuery = "INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())";
        $actionStmt = $conn->prepare($actionQuery);
        $action = "Created announcement #$announcement_id: $title";
        $actionStmt->bind_param("is", $admin_id, $action);
        $actionStmt->execute();
        
        // Send notification to users if enabled
        if ($send_notification) {
            // Get all users (excluding the current admin)
            $userQuery = "SELECT user_id FROM users WHERE user_id != ?";
            $userStmt = $conn->prepare($userQuery);
            $userStmt->bind_param("i", $admin_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            // Insert notifications for each user
            $notifyQuery = "INSERT INTO notifications (user_id, related_id, related_type, message, created_at) VALUES (?, ?, 'admin_action', ?, NOW())";
            $notifyStmt = $conn->prepare($notifyQuery);
            
            while ($user = $userResult->fetch_assoc()) {
                $notifyStmt->bind_param("iis", $user['user_id'], $announcement_id, $notification_text);
                $notifyStmt->execute();
            }
            
            // Log notification activity
            $logQuery = "INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())";
            $logStmt = $conn->prepare($logQuery);
            $logAction = "Sent notification about announcement #$announcement_id to all users";
            $logStmt->bind_param("is", $admin_id, $logAction);
            $logStmt->execute();
        }
        
        ob_end_clean(); // Clean output buffer before redirecting
        header("Location: view_announcement.php?msg=Announcement posted successfully" . ($send_notification ? " and notifications sent" : ""));
        exit();
    } else {
        $error = "Error posting announcement.";
    }
}
ob_end_flush(); // Ensure no extra output is sent
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Announcement</title>
    <style>
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --border-color: #ddd;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: #f9f9f9;
            padding: 0;
            margin: 0;
        }
        
        .container {

            max-width: 1000px; /* Increased width */
            margin: 40px auto; /* Added top/bottom margin */
            padding: 30px; /* Increased padding */
            
        }
        
        .admin-alert {
            background-color: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 12px 18px; /* Larger padding */
            margin-bottom: 25px;
            font-size: 16px; /* Larger font */
        }
        
        h2 {
            color: var(--primary-color);
            margin-bottom: 30px; /* Increased spacing */
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
            font-size: 28px; /* Larger heading */
        }
        

        .card {
            max-width: 800px; /* Adjust as needed */
            width: 100%;
            margin: 0 auto;
            }

            /* Ensure form inputs don't exceed the width */
            .form-group input[type="text"],
            .form-group textarea {
                max-width: 100%; /* Prevents overflowing */
            }
                    
        .form-group {
            margin-bottom: 30px; /* Increased spacing between fields */
            max-width: 10000;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 18px; /* Larger label text */
            color: white;
        }
        
        input[type="text"], textarea {
            width: 100%;
            padding: 15px; /* Larger input padding */
            border: 1px solid var(--border-color);
            border-radius: 6px; /* Increased radius */
            font-family: inherit;
            font-size: 18px; /* Larger font */
            transition: border-color 0.3s;
        }
        
        textarea {
            min-height: 300px; /* Larger textarea */
            resize: vertical;
        }
        
        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .file-input-container {
            position: relative;
            margin-top: 15px;
   
        }
        
        .file-input-button {
            display: inline-block;
            background-color: var(--light-gray);
            color: var(--text-color);
            padding: 12px 20px; /* Larger padding */
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            border: 1px solid var(--border-color);
        }
        
        .file-input-button:hover {
            background-color: #e9e9e9;
        }
        
        input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-name {
            display: inline-block;
            margin-left: 12px;
            font-size: 16px;
            color: #666;
        }
        
        .preview-container {
            margin-top: 20px;
            display: none; /* Hidden by default */
            text-align: center;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 350px; /* Larger preview */
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }
        
        .button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 15px 30px; /* Larger button */
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px; /* Larger text */
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .button:hover {
            background-color: var(--primary-dark);
        }
        
        .error-message {
            color: var(--error-color);
            background-color: rgba(231, 76, 60, 0.1);
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid var(--error-color);
            font-size: 16px;
        }
        
        /* Notification section styles */
        .notification-section {
            background-color: #f8fffe;
            border: 1px solid #d1ede8;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        
        .notification-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .notification-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c9c88;
            margin: 0;
        }
        
        .notification-toggle {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .toggle-label {
            margin-right: 15px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #2c9c88;
        }
        
        input:focus + .slider {
            box-shadow: 0 0 1px #2c9c88;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .notification-text {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-alert">
            <strong>Admin Access</strong> - You are logged in as an administrator with announcement creation privileges.
        </div>
        
        <h2>Create New Announcement</h2>
        
        <div id="errorContainer" class="error-message" style="display: none;">
            Error message goes here
        </div>
        
        <div class="card">
            <form id="announcementForm" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Announcement Title:</label>
                    <input type="text" id="title" name="title" required placeholder="Enter a descriptive title">
                </div>
                
                <div class="form-group">
                    <label for="content">Announcement Content:</label>
                    <textarea id="content" name="content" required placeholder="Write the full announcement content here..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Featured Image (Optional):</label>
                    <div class="file-input-container">
                        <div class="file-input-button">Choose Image</div>
                        <input type="file" id="image" name="image" accept="image/*">
                        <span class="file-name">No file chosen</span>
                    </div>
                    <div class="preview-container">
                        <img id="imagePreview" class="preview-image" src="#" alt="Image Preview">
                    </div>
                </div>
                
                <!-- Notification Section -->
                <div class="notification-section">
                    <div class="notification-header">
                        <h3 class="notification-title">User Notification Settings</h3>
                    </div>
                    
                    <div class="notification-toggle">
                        <span class="toggle-label">Send notification to all users:</span>
                        <label class="switch">
                            <input type="checkbox" id="notificationToggle" name="send_notification" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div id="notificationTextContainer" class="form-group notification-text">
                        <label for="notification_text">Notification Message (Optional):</label>
                        <input type="text" id="notification_text" name="notification_text" 
                               placeholder="Leave blank to use 'New announcement: [title]'">
                        <p style="margin-top: 10px; color: #666; font-size: 14px;">
                            This message will be sent to all users as a notification.
                        </p>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 40px;">
                    <button type="submit" class="button">Publish Announcement</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Show error message if provided from the server
        const urlParams = new URLSearchParams(window.location.search);
        const errorMsg = urlParams.get('error');
        if (errorMsg) {
            const errorContainer = document.getElementById('errorContainer');
            errorContainer.textContent = errorMsg;
            errorContainer.style.display = 'block';
        }
        
        // Image preview functionality
        const imageInput = document.getElementById('image');
        const fileNameSpan = document.querySelector('.file-name');
        const previewContainer = document.querySelector('.preview-container');
        const imagePreview = document.getElementById('imagePreview');
        
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                fileNameSpan.textContent = fileName;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                reader.readAsDataURL(this.files[0]);
            } else {
                fileNameSpan.textContent = 'No file chosen';
                previewContainer.style.display = 'none';
            }
        });
        
        // Notification toggle functionality
        const notificationToggle = document.getElementById('notificationToggle');
        const notificationTextContainer = document.getElementById('notificationTextContainer');
        
        // Show/hide notification text field based on toggle
        notificationToggle.addEventListener('change', function() {
            if (this.checked) {
                notificationTextContainer.style.display = 'block';
            } else {
                notificationTextContainer.style.display = 'none';
            }
        });
        
        // Initialize notification text container visibility
        if (notificationToggle.checked) {
            notificationTextContainer.style.display = 'block';
        }
        
        // Form validation
        const form = document.getElementById('announcementForm');
        form.addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const content = document.getElementById('content').value.trim();
            
            if (!title || !content) {
                e.preventDefault();
                const errorContainer = document.getElementById('errorContainer');
                errorContainer.textContent = 'Please fill out all required fields.';
                errorContainer.style.display = 'block';
            }
        });
    </script>
</body>
</html>