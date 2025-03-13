<?php
ob_start();
include '../includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Item ID not provided.");
}

$id = intval($_GET['id']); // Ensure it's an integer

// Fetch item details
try {
    $stmt = $conn->prepare("SELECT * FROM rental_items WHERE item_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    if (!$item) {
        die("Error: Item not found.");
    }
} catch (mysqli_sql_exception $e) {
    die("Error fetching item: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['rental_price']);
    $quantity = intval($_POST['quantity']); // Get quantity from form
    $imagePath = $item['image_path']; // Keep old image by default

    // Validate quantity
    if ($quantity < 1) {
        die("Error: Quantity must be at least 1.");
    }

    // Handle image upload if a new file is provided
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'item_upload' . DIRECTORY_SEPARATOR;
        $fileName = basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $imagePath = 'item_upload/' . $fileName; // Store relative path
            } else {
                die("Error: Failed to upload new image.");
            }
        } else {
            die("Error: Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.");
        }
    }

    // Update item in the database
    try {
        $stmt = $conn->prepare("UPDATE rental_items SET name = ?, description = ?, rental_price = ?, quantity = ?, image_path = ? WHERE item_id = ?");
        $stmt->bind_param("ssdisi", $name, $description, $price, $quantity, $imagePath, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: index_item.php");
        exit;
    } catch (mysqli_sql_exception $e) {
        die("Error updating item: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Item</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Edit Item</h1>
    <form action="edit_item.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data">
        <label for="name">Name:</label>
        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
        <br>
        <label for="description">Description:</label>
        <textarea name="description" id="description" required><?php echo htmlspecialchars($item['description']); ?></textarea>
        <br>
        <label for="rental_price">Price:</label>
        <input type="number" name="rental_price" id="rental_price" step="0.01" value="<?php echo htmlspecialchars($item['rental_price']); ?>" required>
        <br>
        <label for="quantity">Quantity:</label>
        <input type="number" name="quantity" id="quantity" min="1" value="<?php echo htmlspecialchars($item['quantity']); ?>" required>
        <br>
        <label for="image">Current Image:</label>
        <br>
        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" width="100" alt="Current Image">
        <br>
        <label for="image">Change Image:</label>
        <input type="file" name="image" id="image" accept="image/*">
        <br>
        <input type="submit" value="Update Item">
    </form>
</body>
</html>
