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

    // Execute the query and get the property ID
    if ($stmt->execute()) {
        $propertyId = $stmt->insert_id;

        // Handle multiple image uploads
        if (!empty($_FILES["images"]["name"][0])) {
            $targetDir = "../uploads/property/";
            foreach ($_FILES["images"]["name"] as $key => $name) {
                $fileName = time() . "_" . basename($name);
                $targetFilePath = $targetDir . $fileName;
                $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

                if (in_array(strtolower($fileType), ['jpg', 'jpeg', 'png', 'gif'])) {
                    if (move_uploaded_file($_FILES["images"]["tmp_name"][$key], $targetFilePath)) {
                        $conn->query("INSERT INTO property_images (property_id, image_url) VALUES ($propertyId, '$targetFilePath')");
                    }
                }
            }
        }

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
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            font-family: 'Poppins', sans-serif;
            padding: 20px 0;
            color: #495057;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
            color: #212529;
            font-weight: 600;
            position: relative;
        }
        
        .page-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: #3b82f6;
            margin: 10px auto;
            border-radius: 2px;
        }
        
        .container {
            max-width: 850px;
            margin: 30px auto;
            padding: 0;
        }
        
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 25px;
            border-bottom: none;
            position: relative;
        }
        
        .card-header h2 {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-header i {
            margin-right: 12px;
            font-size: 24px;
        }
        
        .card-body {
            padding: 30px 40px;
            background: white;
        }
        
        .form-label {
            color: #495057;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 15px;
        }
        
        .form-control, .form-select {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }
        
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            border-color: #3b82f6;
            background-color: #fff;
        }
        
        textarea.form-control {
            min-height: 120px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb, #1e40af);
        }
        
        .mb-3 {
            margin-bottom: 20px !important;
        }
        
        .preview-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
            text-align: center;
            border: 2px dashed #e0e0e0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            transition: all 0.3s;
        }
        
        .preview-container:hover {
            border-color: #3b82f6;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: none;
        }
        
        .upload-icon {
            font-size: 48px;
            color: #adb5bd;
            margin-bottom: 10px;
        }
        
        .upload-text {
            color: #6c757d;
            font-size: 14px;
        }
        
        .form-group-icon {
            position: relative;
        }
        
        .form-group-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 18px;
        }
        
        .form-group-icon .form-control {
            padding-left: 45px;
        }
        
        .form-floating {
            position: relative;
        }
        
        .form-floating > label {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            padding: 1rem 0.75rem;
            pointer-events: none;
            border: 1px solid transparent;
            transform-origin: 0 0;
            transition: opacity 0.1s ease-in-out, transform 0.1s ease-in-out;
        }
        
        @media (max-width: 768px) {
            .card-body {
                padding: 20px;
            }
            .container {
                padding: 0 15px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h1 class="page-title">Property Management</h1>
    
    <div class="card">
        <div class="card-header text-center">
            <h2><i class="bi bi-house-door"></i> Add New Property</h2>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data" id="propertyForm">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-tag"></i> Property Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Enter a descriptive title" required>
                </div>

                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-card-text"></i> Description</label>
                    <textarea name="description" class="form-control" placeholder="Describe the property features, location and amenities" required></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-currency"></i> Price (PHP)</label>
                        <input type="number" step="0.01" name="price" class="form-control" placeholder="Enter price" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-house"></i> Type</label>
                        <select name="type" class="form-select">
                            <option value="house">House</option>
                            <option value="apartment">Apartment</option>
                            <option value="lot">Lot</option>
                            <option value="commercial">Commercial</option>
                            <option value="condo">Condominium</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-check-circle"></i> Status</label>
                        <select name="status" class="form-select">
                            <option value="available">For Sale</option>
                            <option value="For rent">For Rent</option>

                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-image"></i> Property Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*" id="imageInput">
                    
                    <div class="preview-container mt-3">
                        <i class="bi bi-cloud-arrow-up upload-icon" id="uploadIcon"></i>
                        <p class="upload-text" id="uploadText">Drag and drop an image or click to browse</p>
                        <img id="imagePreview" class="preview-image">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Upload Images</label>
                    <input type="file" name="images[]" class="form-control" multiple>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-plus-circle me-2"></i> Add Property
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
                
                // Hide the upload icon and text
                document.getElementById("uploadIcon").style.display = "none";
                document.getElementById("uploadText").style.display = "none";
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Optional: Make the entire preview container clickable to trigger file input
    document.querySelector(".preview-container").addEventListener("click", function() {
        document.getElementById("imageInput").click();
    });



                    /**
                 * Page Action Handler - Global utility for managing page actions and reloads
                 * Add this to all PHP files to ensure consistent user experience
                 */
 
</script>

</body>
</html>
<?php include '../includes/footer.php'; ?>