<?php

include '../includes/header.php';

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied. Only administrators can edit the facilities.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: Facility ID is missing.");
}

$facility_id = (int) $_GET['id']; // Convert ID to integer for safety

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $facility_id = $_POST['facility_id'];
    $name = $_POST['name'];
    $availability_status = $_POST['availability_status'];
    $description = $_POST['description'];

    // Check if an image is uploaded
    if (!empty($_FILES['facility_image']['name'])) {
        $upload_dir = '../admin/image_faci/';
        $image_name = time() . "_" . basename($_FILES['facility_image']['name']);
        $image_path = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['facility_image']['tmp_name'], $image_path)) {
            $query = "UPDATE amenities SET name = ?, availability_status = ?, description = ?, image_url = ? WHERE facility_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $name, $availability_status, $description, $image_path, $facility_id);
        } else {
            die("Error uploading image.");
        }
    } else {
        $query = "UPDATE amenities SET name = ?, availability_status = ?, description = ? WHERE facility_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssi", $name, $availability_status, $description, $facility_id);
    }

    if ($stmt->execute()) {
        header("Location: /subdisystem/view_faci.php?msg=Facility updated successfully");
        exit();
    } else {
        echo "<p style='color: red;'>Error updating facility.</p>";
    }
}

// Fetch facility details
$query = "SELECT * FROM amenities WHERE facility_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$facility = $stmt->get_result()->fetch_assoc();

if (!$facility) {
    die("Error: Facility not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Facility</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .edit-facility-container {
            max-width: 800px;
            margin: 30px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 25px;
            font-size: 24px;
        }
        
        .current-image {
            text-align: center;
            margin-bottom: 25px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        
        .current-image img {
            max-width: 100%;
            max-height: 250px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 4px;
        }
        
        .current-image p {
            margin-top: 10px;
            color: #666;
            font-style: italic;
        }
        
        form {
            display: flex;
            flex-direction: column;
        }
        
        label {
            margin-top: 15px;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="text"],
        select,
        textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        select {
            background-color: white;
            cursor: pointer;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        input[type="file"] {
            padding: 10px 0;
            margin-bottom: 15px;
        }
        
        .image-preview {
            margin: 15px 0;
            text-align: center;
            display: none;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 250px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 4px;
        }
        
        .image-notice {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
            margin-bottom: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-available {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-unavailable {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .status-maintenance {
            background-color: #fff8e1;
            color: #f57f17;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        button[type="submit"] {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            flex: 1;
            transition: background-color 0.3s;
        }
        
        button[type="submit"]:hover {
            background-color: #2980b9;
        }
        
        .cancel-button {
            background-color: #95a5a6;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            flex: 1;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.3s;
            display: inline-block;
        }
        
        .cancel-button:hover {
            background-color: #7f8c8d;
        }
        
        .admin-note {
            margin-top: 20px;
            font-size: 14px;
            color: #777;
            text-align: center;
            font-style: italic;
        }
        .wow
        {
            padding-top: 25%;
        }

    </style>
</head>
<body>
    <!-- Header would be included here -->
    
    <div class="edit-facility-container">
        <h2 class="wow">Edit Facility</h2>
        
        <!-- Current image display -->
        <div class="current-image">
            <img src="<?= htmlspecialchars($facility['image_url']); ?>" alt="Current facility image">
            <p>Current facility image</p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="facility_id" value="<?= htmlspecialchars($facility['facility_id']); ?>">

            <label for="name">Facility Name:</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($facility['name']); ?>" required>

            <label for="availability_status">Availability Status:</label>
            <select id="availability_status" name="availability_status" required>
                <option value="available" <?= $facility['availability_status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                <option value="unavailable" <?= $facility['availability_status'] == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                <option value="under maintenance" <?= $facility['availability_status'] == 'under maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
            </select>
            
            <div class="status-display">
                <span>Current status:</span>
                <?php 
                $statusClass = "";
                switch($facility['availability_status']) {
                    case 'available':
                        $statusClass = "status-available";
                        break;
                    case 'unavailable':
                        $statusClass = "status-unavailable";
                        break;
                    case 'under maintenance':
                        $statusClass = "status-maintenance";
                        break;
                }
                ?>
                <span class="status-badge <?= $statusClass; ?>"><?= ucfirst(htmlspecialchars($facility['availability_status'])); ?></span>
            </div>

            <label for="description">Description:</label>
            <textarea id="description" name="description" required><?= htmlspecialchars($facility['description']); ?></textarea>

            <label for="facility_image">Upload New Image:</label>
            <input type="file" id="facility_image" name="facility_image" accept="image/*" onchange="previewImage(this)">
            <div class="image-notice">Leave empty to keep the current image</div>
            
            <div class="image-preview" id="imagePreview">
                <img src="/api/placeholder/400/320" alt="New Image Preview" id="preview">
                <p>New image preview</p>
            </div>

            <div class="action-buttons">
                <a href="/subdisystem/view_faci.php" class="cancel-button">Cancel</a>
                <button type="submit">Update Facility</button>
            </div>
        </form>
        
        <div class="admin-note">Note: Only administrators can edit facility information.</div>
    </div>
    
    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview');
            const previewContainer = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                previewContainer.style.display = 'none';
            }
        }
    </script>
    
    <!-- Footer would be included here -->
</body>
</html>