<?php
include '../includes/header.php';

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /subdisystem/index.php");
    exit("Access Denied. Only administrators can view this page.");
}

// Retrieve all facilities from the database
$query = "SELECT * FROM amenities ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$facilities = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Facilities Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <style>
        body{
            background-color:white;
        }
        .facility-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .facility-actions {
            white-space: nowrap;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .status-available {
            background-color: #d4edda;
            color: #155724;
        }
        .status-unavailable {
            background-color: #f8d7da;
            color: #721c24;
        }
        .page-header {
            background-color: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: black;
        }
        .description-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="page-header">
            <h2><i class="fas fa-building me-2"></i>Facilities Management</h2>
            <a href="/subdisystem/admin/create_faci.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add New Facility
            </a>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['alert_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); unset($_SESSION['alert_type']); ?>
        <?php endif; ?>
        
        <?php if (count($facilities) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Image</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($facilities as $facility): ?>
                            <tr>
                                <td><?= htmlspecialchars($facility['facility_id']); ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($facility['name']); ?></td>
                                <td class="description-cell" title="<?= htmlspecialchars($facility['description']); ?>">
                                    <?= htmlspecialchars($facility['description']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge <?= strtolower($facility['availability_status']) === 'available' ? 'status-available' : 'status-unavailable' ?>">
                                        <?= htmlspecialchars($facility['availability_status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <img src="/subdisystem/admin/image_faci/<?= htmlspecialchars(basename($facility['image_url'])); ?>" 
                                         alt="<?= htmlspecialchars($facility['name']); ?>" 
                                         class="facility-img"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageModal<?= $facility['facility_id']; ?>"
                                         style="cursor: pointer;">
                                </td>
                                <td class="text-center facility-actions">
                                    <a href="/subdisystem/admin/edit_faci.php?id=<?= $facility['facility_id']; ?>" class="btn btn-sm btn-primary me-1">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                    <a href="/subdisystem/admin/delete_faci.php?id=<?= $facility['facility_id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this facility? This action cannot be undone.');">
                                        <i class="fas fa-trash-alt me-1"></i>Delete
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Image Modal for each facility -->
                            <div class="modal fade" id="imageModal<?= $facility['facility_id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?= htmlspecialchars($facility['name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="/subdisystem/admin/image_faci/<?= htmlspecialchars(basename($facility['image_url'])); ?>" 
                                                 alt="<?= htmlspecialchars($facility['name']); ?>" 
                                                 class="img-fluid rounded">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                <i class="fas fa-info-circle me-2"></i>No facilities have been added yet.
                <div class="mt-3">
                    <a href="/subdisystem/admin/add_facility.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Your First Facility
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>