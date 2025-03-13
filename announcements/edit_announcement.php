<?php
ob_start(); // Start output buffering
include '../includes/header.php';

// Ensure only admins can access
if ($_SESSION['role'] !== 'admin') {
    die("Access Denied. Only administrators can edit announcements.");
}

if (!isset($_GET['id'])) {
    die("Invalid announcement ID.");
}

$announcement_id = $_GET['id'];

// Fetch the announcement details
$query = "SELECT * FROM announcements WHERE announcement_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $announcement_id);
$stmt->execute();
$announcement = $stmt->get_result()->fetch_assoc();

if (!$announcement) {
    die("Announcement not found.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];

    // Handle Image Upload
    $image_url = $announcement['image_url']; // Keep the existing image
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . "/subdisystem/announcements/uploads/";

        // Ensure the directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($fileType), $allowedTypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $image_url = "/subdisystem/announcements/uploads/" . $fileName;
            } else {
                $error = "Error uploading image.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF allowed.";
        }
    }

    // Update the announcement
    $updateQuery = "UPDATE announcements SET title=?, content=?, image_url=? WHERE announcement_id=?";
    $stmt = $conn->prepare($updateQuery);
    
    if (!$stmt) {
        die("Error preparing the statement: " . $conn->error);
    }

    $stmt->bind_param("sssi", $title, $content, $image_url, $announcement_id);

    if ($stmt->execute()) {
        ob_end_clean(); // Clean output buffer before redirecting
        header("Location: view_announcement.php?msg=Announcement updated successfully");
        exit();
    } else {
        $error = "Error updating announcement: " . $stmt->error;
    }
}
ob_end_flush(); // Ensure no extra output is sent
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Announcement</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
</head>
<body>
    <h2>Edit Announcement</h2>
    
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <form method="post" enctype="multipart/form-data">
        <label>Title:</label>
        <input type="text" name="title" value="<?= htmlspecialchars($announcement['title']); ?>" required>

        <label>Content:</label>
        <textarea name="content" required><?= htmlspecialchars($announcement['content']); ?></textarea>

        <label>Current Image:</label>
        <?php if (!empty($announcement['image_url'])): ?>
            <img src="<?= htmlspecialchars($announcement['image_url']); ?>" alt="Announcement Image" width="150">
        <?php else: ?>
            <p>No image available.</p>
        <?php endif; ?>

        <label>New Image:</label>
        <input type="file" name="image" accept="image/*">

        <button type="submit">Update Announcement</button>
        <a href="view_announcement.php">Cancel</a>
    </form>
</body>

<style> /* General Page Styling */
body {
    background-color: #121212;
    color: #ffffff;
    font-family: 'Arial', sans-serif;
    margin: 0;
    padding: 0;
}

/* Main Container - Wider Layout */
.booking-container {
    max-width: 1400px; /* Increased width for more space */
    margin: 50px auto;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 30px;
    padding: 30px;
}

/* Header Styling */
.booking-header {
    width: 100%;
    text-align: center;
    margin-bottom: 2rem;
    border-bottom: 3px solid #ffffff;
    padding-bottom: 1.5rem;
    font-size: 28px;
    font-weight: bold;
}

/* Booking Form Section */
.booking-form {
    flex: 1;
    background-color: #1c1c1c;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0px 6px 12px rgba(255, 255, 255, 0.1);
    min-width: 500px;
}

/* Form Field Styling */
.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    font-size: 18px;
    margin-bottom: 8px;
}

select, input, textarea {
    width: 100%;
    padding: 15px;
    border: none;
    border-radius: 8px;
    background: #2c2c2c;
    color: #ffffff;
    font-size: 18px;
}

textarea {
    resize: none;
    height: 120px;
}

select:focus, input:focus, textarea:focus {
    border: 2px solid #17a2b8;
    outline: none;
}

/* Large Button */
button {
    width: 100%;
    background-color: #17a2b8;
    color: white;
    padding: 15px;
    font-size: 20px;
    font-weight: bold;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.3s, transform 0.2s;
}

button:hover {
    background-color: #138496;
    transform: scale(1.05);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .booking-container {
        flex-direction: column;
        align-items: center;
    }

    .booking-form, .facility-list {
        width: 100%;
    }

}

@media (max-width: 768px) {
    .facility-card {
        flex: 1 1 100%;
    }

    .booking-form {
        min-width: unset;
    }
}
</style>
</html>
