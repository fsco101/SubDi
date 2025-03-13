<?php

include '../includes/header.php';

if (!isset($_GET['id'])) {
    die("Invalid property ID.");
}

$property_id = $_GET['id'];
$query = "SELECT * FROM properties WHERE property_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    die("Property not found.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $type = $_POST['type'];
    $status = $_POST['status'];

    // Handle image upload
    $imagePath = $property['image_url']; // Keep existing image
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "../uploads/property/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true); // Ensure directory exists
        }

        $fileName = time() . "_" . basename($_FILES["image"]["name"]); // Unique filename
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($fileType), $allowedTypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $imagePath = $targetFilePath;
            } else {
                $error = "Error uploading the image.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF allowed.";
        }
    }

    // Update property details
    $updateQuery = "UPDATE properties SET title=?, description=?, price=?, type=?, status=?, image_url=? WHERE property_id=?";
    $stmt = $conn->prepare($updateQuery);

    if (!$stmt) {
        die("Error preparing the statement: " . $conn->error);
    }

    $stmt->bind_param("ssdsssi", $title, $description, $price, $type, $status, $imagePath, $property_id);

    if ($stmt->execute()) {
        echo "<script>alert('Property updated successfully!'); window.location.href='index_properties.php';</script>";
        exit();
    } else {
        $error = "Error updating property: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Property</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
</head>
<body>
    <h2>Edit Property</h2>
    
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <form method="post" enctype="multipart/form-data">
        <label>Title:</label>
        <input type="text" name="title" value="<?= htmlspecialchars($property['title']); ?>" required>
        
        <label>Description:</label>
        <textarea name="description" required><?= htmlspecialchars($property['description']); ?></textarea>

        <label>Price:</label>
        <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($property['price']); ?>" required>

        <label>Type:</label>
        <select name="type" required>
            <option value="house" <?= ($property['type'] == 'house') ? 'selected' : ''; ?>>House</option>
            <option value="apartment" <?= ($property['type'] == 'apartment') ? 'selected' : ''; ?>>Apartment</option>
            <option value="lot" <?= ($property['type'] == 'lot') ? 'selected' : ''; ?>>Lot</option>
        </select>

        <label>Status:</label>
        <select name="status" required>
            <option value="available" <?= ($property['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
            <option value="sold" <?= ($property['status'] == 'sold') ? 'selected' : ''; ?>>Sold</option>
            <option value="For rent" <?= ($property['status'] == 'For rent') ? 'selected' : ''; ?>>For rent</option>
        </select>

        <label>Current Image:</label>
        <?php if (!empty($property['image_url'])): ?>
            <img src="<?= htmlspecialchars($property['image_url']); ?>" alt="Property Image" width="150">
        <?php else: ?>
            <p>No image available.</p>
        <?php endif; ?>

        <label>New Image:</label>
        <input type="file" name="image" accept="image/*">
        
        <button type="submit">Update Property</button>
        <a href="index_properties.php">Cancel</a>
    </form>
</body>
</html>
