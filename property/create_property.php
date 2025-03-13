<?php
ob_start();
include '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the data from the form
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $type = $_POST['type'];
    $status = $_POST['status'];

    // Get the user ID from the session
    session_start(); // Ensure session is started
    if (!isset($_SESSION['user_id'])) {
        die("Access Denied. Please log in.");
    }
    $userId = $_SESSION['user_id'];

    // Handle image upload
    $targetDir = "../uploads/property/property_images/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $imagePath = "";
    if (!empty($_FILES["image"]["name"])) {
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;

        // Move the uploaded file to the target directory
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            $imagePath = $targetFilePath; // Save the path to the image
        } else {
            $error = "Error uploading image.";
        }
    }

    // Insert property into the database including user_id
    $query = "INSERT INTO properties (title, description, price, type, status, image_url, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssdsssi", $title, $description, $price, $type, $status, $imagePath, $userId);

    // Execute the query and redirect on success
    if ($stmt->execute()) {
        header("Location: show_property.php?msg=Property added successfully");
        exit();
    } else {
        $error = "Error adding property.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #e9ecef;
            font-family: 'Arial', sans-serif;
        }
        .container {
            max-width: 750px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
        }
        .card-header {
            background-color: #007bff;
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .form-label
        {
            color:white;
        }

        .form-control, .form-select {
            border-radius: 8px;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .preview-image {
            width: 100%;
            height: auto;
            max-height: 250px;
            object-fit: cover;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        @media (max-width: 576px) {
            .container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="card-header text-center">
            <h2><i class="bi bi-house-door"></i> Add Property</h2>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" id="propertyForm">
                <div class="mb-3">
                    <label class="form-label">Title:</label>
                    <input type="text" name="title" class="form-control" placeholder="Enter property title" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description:</label>
                    <textarea name="description" class="form-control" placeholder="Enter property description" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Price:</label>
                    <input type="number" step="0.01" name="price" class="form-control" placeholder="Enter price" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Type:</label>
                    <select name="type" class="form-select">
                        <option value="house">House</option>
                        <option value="apartment">Apartment</option>
                        <option value="lot">Lot</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status:</label>
                    <select name="status" class="form-select">
                        <option value="available">Available</option>
                        <option value="sold">Sold</option>
                        <option value="For rent">For Rent</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Upload Image:</label>
                    <input type="file" name="image" class="form-control" accept="image/*" id="imageInput">
                    <img id="imagePreview" class="preview-image">
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-plus-circle"></i> Add Property
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById("imageInput").addEventListener("change", function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById("imagePreview");
                preview.src = e.target.result;
                preview.style.display = "block";
            }
            reader.readAsDataURL(file);
        }
    });
</script>

</body>
</html>