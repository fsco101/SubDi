<?php
ob_start();
include '../includes/header.php';

$error = ""; // Initialize error variable

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ensure POST data exists
    if (empty($_POST)) {
        die("Error: No POST data received. Check if the form method is correct.");
    }

    // Get form data safely
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $price = isset($_POST['rental_price']) ? floatval($_POST['rental_price']) : 0.00;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    // Validate required fields
    if (empty($name) || empty($description) || $price <= 0 || $quantity < 1) {
        $error = "Please fill in all required fields with valid values.";
    } else {
        // Handle image upload
        $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'item_upload' . DIRECTORY_SEPARATOR; // Flexible path

        // Ensure directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');

        if (in_array($fileType, $allowTypes)) {
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                if (is_uploaded_file($_FILES['image']['tmp_name'])) {
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                        // Insert item into database
                        try {
                            $stmt = $conn->prepare("INSERT INTO rental_items (name, description, rental_price, quantity, availability_status, image_path) 
                                                    VALUES (?, ?, ?, ?, 'available', ?)");
                            $stmt->bind_param("ssdss", $name, $description, $price, $quantity, $targetFilePath);
                            $stmt->execute();
                            $stmt->close();

                            header("Location: index_item.php");
                            exit;
                        } catch (mysqli_sql_exception $e) {
                            $error = "Error adding item: " . $e->getMessage();
                        }
                    } else {
                        $error = "Error moving the uploaded file.";
                    }
                } else {
                    $error = "The file was not uploaded via HTTP POST.";
                }
            } else {
                $error = "File upload error. Error code: " . $_FILES['image']['error'];
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Item</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-container {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color:black;
        }
        
        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        
        textarea {
            height: 120px;
            resize: vertical;
        }
        
        input[type="file"] {
            padding: 10px 0;
        }
        
        .image-preview {
            margin-top: 10px;
            text-align: center;
            display: none;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .submit-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #2980b9;
        }
        .padding
        {
            padding-top: 25%;
        }

    </style>
</head>
<body>
  
    
    <div class="form-container">
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <h1 class="padding">Add New Item</h1>
        <form action="create_item.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea name="description" id="description" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="rental_price">Price ($):</label>
                <input type="number" name="rental_price" id="rental_price" step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="quantity">Quantity:</label>
                <input type="number" name="quantity" id="quantity" min="1" value="1" required>
            </div>
            
            <div class="form-group">
                <label for="image">Image:</label>
                <input type="file" name="image" id="image" accept="image/*" required onchange="previewImage(this)">
                <div class="image-preview" id="imagePreview">
                    <img src="/api/placeholder/400/320" alt="Image Preview" id="preview">
                </div>
            </div>
            
            <button type="submit" class="submit-btn">Add Item</button>
        </form>
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
            }
        }

                        /**
                 * Page Action Handler - Global utility for managing page actions and reloads
                 * Add this to all PHP files to ensure consistent user experience
                 */
 
    </script>
</body>
</html>
