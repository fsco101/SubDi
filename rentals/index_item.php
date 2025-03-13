<?php
include '../includes/header.php';

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit("Access Denied. Only administrators are allowed.");
}

// Fetch all rental items
try {
    $sql = "SELECT * FROM rental_items ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching items: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Items Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style/style.css">
    <style>


        body{
            background-color: white;
        }

        .item-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            cursor: pointer;
        }
        .item-img:hover {
            transform: scale(1.05);
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .dashboard-header {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: black;
        }
        .quantity-badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-weight: 500;
        }
        .in-stock {
            background-color: #d4edda;
            color: #155724;
        }
        .low-stock {
            background-color: #fff3cd;
            color: #856404;
        }
        .out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
        }
        .description-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .action-buttons {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="dashboard-header">
            <h1><i class="fas fa-boxes me-2"></i>Rental Items Management</h1>
            <a href="create_item.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Add New Item
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (!empty($items)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-center">Price</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-center">Image</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($item['name']); ?></td>
                                <td class="description-cell" title="<?= htmlspecialchars($item['description']); ?>">
                                    <?= htmlspecialchars($item['description']); ?>
                                </td>
                                <td class="text-center">₱<?= number_format($item['rental_price'], 2); ?></td>
                                <td class="text-center">
                                    <?php
                                    $quantity = (int)$item['quantity'];
                                    $badgeClass = 'in-stock';
                                    if ($quantity <= 0) {
                                        $badgeClass = 'out-of-stock';
                                    } elseif ($quantity <= 5) {
                                        $badgeClass = 'low-stock';
                                    }
                                    ?>
                                    <span class="quantity-badge <?= $badgeClass; ?>">
                                        <?= htmlspecialchars($item['quantity']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($item['image_path'])): ?>
                                        <img src="<?= htmlspecialchars('item_upload/' . basename($item['image_path'])); ?>"
                                             alt="<?= htmlspecialchars($item['name']); ?>"
                                             class="item-img"
                                             data-bs-toggle="modal"
                                             data-bs-target="#imageModal<?= $item['item_id']; ?>">
                                    <?php else: ?>
                                        <div class="text-muted">
                                            <i class="fas fa-image fa-2x"></i>
                                            <p class="small">No Image</p>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center action-buttons">
                                    <a href="edit_item.php?id=<?= $item['item_id']; ?>" class="btn btn-sm btn-primary me-1">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                    <a href="delete_item.php?id=<?= $item['item_id']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this item? This action cannot be undone.');">
                                        <i class="fas fa-trash-alt me-1"></i>Delete
                                    </a>
                                </td>
                            </tr>

                            <!-- Image Modal -->
                            <?php if (!empty($item['image_path'])): ?>
                            <div class="modal fade" id="imageModal<?= $item['item_id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?= htmlspecialchars($item['name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="<?= htmlspecialchars('item_upload/' . basename($item['image_path'])); ?>"
                                                 alt="<?= htmlspecialchars($item['name']); ?>"
                                                 class="img-fluid rounded">
                                            <div class="mt-3">
                                                <p><?= htmlspecialchars($item['description']); ?></p>
                                                <p class="fw-bold">Price: ₱<?= number_format($item['rental_price'], 2); ?></p>
                                                <p>Available Quantity: <?= htmlspecialchars($item['quantity']); ?></p>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="edit_item.php?id=<?= $item['item_id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-edit me-1"></i>Edit Item
                                            </a>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center p-5" role="alert">
                <i class="fas fa-info-circle fa-3x mb-3"></i>
                <h4>No Rental Items Available</h4>
                <p class="mb-4">Start by adding your first rental item to the inventory.</p>
                <a href="create_item.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Add Your First Item
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>