    <?php
    include '../includes/header.php';
    
    // Ensure only admin can access
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        die("Access Denied. Only administrators can add facilities.");
    }
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = $_POST['name'];
        $availability_status = $_POST['availability_status'];
        $description = $_POST['description'];

        // Handle Image Upload
        if (isset($_FILES['image_faci']) && $_FILES['image_faci']['error'] == 0) {
            // Set the upload folder path
            $upload_dir = '../admin/image_faci/';
            $image_name = basename($_FILES['image_faci']['name']);
            $image_path = $upload_dir . $image_name;

            // Check if the file is an actual image
            $image_type = mime_content_type($_FILES['image_faci']['tmp_name']);
            if (strpos($image_type, 'image') === false) {
                $error = "The uploaded file is not a valid image.";
            } else {
                // Ensure the image name is unique by appending a timestamp
                $image_name = time() . '-' . $image_name;
                $image_path = $upload_dir . $image_name;

                // Move the uploaded image to the folder
                if (move_uploaded_file($_FILES['image_faci']['tmp_name'], $image_path)) {
                    // Insert the facility details including image URL into the database
                    $query = "INSERT INTO amenities (name, availability_status, description, image_url) 
                            VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssss", $name, $availability_status, $description, $image_path);

                    if ($stmt->execute()) {
                        $success = "Facility added successfully.";
                    } else {
                        $error = "Error adding facility to the database.";
                    }
                } else {
                    $error = "Error uploading image.";
                }
            }
        } else {
            $error = "No image selected.";
        }
    }
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/subdisystem/style/style.css">

    <title>Add New Facility</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .create-facility-container {
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
            padding-top: 25%;
        }
        
        .notification {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .error {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        form {
            display: flex;
            flex-direction: column;
        }
        
        label {
            margin-top: 15px;
            margin-bottom: 8px;
            font-weight: bold;
            color: white;
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
        
        button[type="submit"] {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
            transition: background-color 0.3s;
        }
        
        button[type="submit"]:hover {
            background-color: #2980b9;
        }
        
        .admin-note {
            margin-top: 20px;
            font-size: 14px;
            color: #777;
            text-align: center;
            font-style: italic;
        }

    </style>
</head>
<body>
   
    
    <div class="create-facility-container">
        <h2>Add New Facility</h2>

        <?php if (isset($error)): ?>
            <div class="notification error"><?= $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="notification success"><?= $success; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <label for="name">Facility Name:</label>
            <input type="text" id="name" name="name" required placeholder="Enter facility name">

            <label for="availability_status">Availability Status:</label>
            <select id="availability_status" name="availability_status" required>
                <option value="">-- Select availability --</option>
                <option value="available">Available</option>
                <option value="unavailable">Unavailable</option>
            </select>

            <label for="description">Description:</label>
            <textarea id="description" name="description" required placeholder="Provide details about this facility"></textarea>

            <label for="image_faci">Facility Image:</label>
            <input type="file" id="image_faci" name="image_faci" accept="image/*" required onchange="previewImage(this)">
            
            <div class="image-preview" id="imagePreview">
                <img src="/api/placeholder/400/320" alt="Facility Image Preview" id="preview">
            </div>

            <button type="submit">Add Facility</button>
        </form>
        
        <div class="admin-note">Note: This form is only accessible to administrators.</div>
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


                        /**
                 * Page Action Handler - Global utility for managing page actions and reloads
                 * Add this to all PHP files to ensure consistent user experience
                 */
 
    </script>
    
</body>
</html>


