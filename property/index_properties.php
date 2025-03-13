<?php
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    die("Access Denied. Please log in.");

    
}

// Fetch properties belonging to the logged-in user
$userId = $_SESSION['user_id'];

$query = "SELECT * FROM properties WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$properties = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Property Listings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/subdisystem/style/style.css"> <!-- Custom styles if necessary -->
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Arial', sans-serif;
        }
        .container {
            margin-top: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            color:Black;
        }
        h2 {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            margin-top: 20px;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        th, td {
            padding: 10px;
            text-align: center;
        }
        td img {
            max-width: 100px;
            border-radius: 5px;
        }
        .action-links a {
            margin: 0 5px;
            text-decoration: none;
            color: #007bff;
        }
        .action-links a:hover {
            text-decoration: underline;
        }
        .no-properties {
            text-align: center;
            font-size: 1.2em;
            color: #777;
        }
        .btn-primary {
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Your Property Listings</h2>
    <a href="create_property.php" class="btn btn-primary">Add New Property</a>

    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Image</th>
                <th>Title</th>
                <th>Description</th>
                <th>Price</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($properties)): ?>
                <tr>
                    <td colspan="7" class="no-properties">No properties found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($properties as $property): ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($property['image_url']); ?>" alt="<?= htmlspecialchars($property['title']); ?>"></td>
                        <td><?= htmlspecialchars($property['title']); ?></td>
                        <td><?= htmlspecialchars($property['description']); ?></td>
                        <td>$<?= number_format($property['price'], 2); ?></td>
                        <td><?= htmlspecialchars($property['type']); ?></td>
                        <td><?= ucfirst(htmlspecialchars($property['status'])); ?></td>
                        <td class="action-links">
                            <a href="edit_property.php?id=<?= $property['property_id']; ?>">Edit</a>
                            <a href="delete_property.php?id=<?= $property['property_id']; ?>" onclick="return confirm('Delete this property?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>