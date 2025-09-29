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
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --light-bg: #f8fafc;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
        }
                
        /* Header styling */
        .page-header {
            background-color: white;
            padding: 1.5rem 0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: auto; /* Allow header to size naturally */
        }
        
        .page-header .container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: transparent;
            box-shadow: none;
            padding: 0.5rem 2rem; /* Added vertical padding */
        }
        
        .page-title {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        /* Main container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }
        
        /* Section header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        /* Button styling */
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
            text-decoration: none;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(59, 130, 246, 0.3);
            background: linear-gradient(135deg, #2563eb, #1e40af);
        }
        
        .btn-primary i {
            margin-right: 0.5rem;
        }

        /* Add this to override hover effects for the Add Property button */
        .btn-add-property {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
            text-decoration: none;
            color: white;
            /* No transition to prevent any hover effects */
            transition: none;
        }

        /* Remove hover effects completely */
        .btn-add-property:hover {
            transform: none;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        /* Property grid layout */
        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
            padding: 0.5rem;
        }
        
        /* Property card styling */
        .property-card {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .property-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .property-img {
            position: relative;
            height: 220px;
            overflow: hidden;
        }
        
        .property-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .property-card:hover .property-img img {
            transform: scale(1.05);
        }
        
        /* Property details section */
        .property-details {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 1.5rem;
            gap: 0.75rem; /* Add consistent spacing between elements */
        }
        
        .property-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #1e293b;
            line-height: 1.4;
        }
        
        .property-description {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }
        
        .property-meta {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .property-price {
            font-weight: 600;
            font-size: 1.25rem;
            color: var(--primary);
        }
        
        .property-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #f1f5f9;
            color: #64748b;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .property-status {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            margin: 1rem 0;
        }
        
        .property-status.available {
            background-color: #dcfce7;
            color: #16a34a;
        }
        
        .property-status.sold {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .property-status.pending {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .property-status.for-rent {
            background-color: #dbeafe;
            color: #2563eb;
        }
        
        .property-status i {
            margin-right: 0.3rem;
            font-size: 0.75rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: auto;
            padding-top: 1rem;
        }
        
        .btn-action {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-edit {
            background-color: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        
        .btn-edit:hover {
            background-color: #f1f5f9;
            color: #334155;
            text-decoration: none;
        }
        
        .btn-delete {
            background-color: #fef2f2;
            color: #ef4444;
            border: 1px solid #fee2e2;
        }
        
        .btn-delete:hover {
            background-color: #fee2e2;
            color: #b91c1c;
            text-decoration: none;
        }
        
        .no-properties {
            text-align: center;
            padding: 3rem 1rem;
            background-color: #f8fafc;
            border-radius: var(--border-radius);
            border: 1px dashed #cbd5e1;
        }
        
        .no-properties i {
            font-size: 3rem;
            color: #94a3b8;
            margin-bottom: 1rem;
            display: block;
        }
        
        .no-properties p {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 140px; /* Increase padding for mobile */
            }
            
            .page-header .container,
            .container {
                padding: 1.5rem;
            }
            
            .page-header .container {
                flex-direction: column;
                padding: 1rem;
            }
            
            .page-title {
                margin-bottom: 0.75rem;
            }
            
            .property-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .property-card {
                max-width: 100%;
            }
        
            .action-buttons {
                flex-direction: row;
            }
        }
        
        /* Fix price/type alignment */
        .d-flex {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            width: 100%;
            margin-bottom: 0.5rem !important;
        }
        
        /* Remove footer background color */
        footer {
            background-color: black !important;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Combined header content -->
    <div class="page-header-content">
        <h1 class="page-title">Property Management</h1>
        <a href="create_property.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Property
        </a>
    </div>

    <div class="section-header">
        <h2 class="section-title">Your Property Listings</h2>
        <div class="filters">
            <!-- You can add filters here in the future -->
        </div>
    </div>

    <?php if (empty($properties)): ?>
        <div class="no-properties">
            <i class="bi bi-house"></i>
            <p>You haven't added any properties yet.</p>
            <a href="create_property.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Your First Property
            </a>
        </div>
    <?php else: ?>
        <div class="property-grid">
            <?php foreach ($properties as $property): ?>
                <div class="property-card">
                    <div class="property-img">
                        <?php if (!empty($property['image_url'])): ?>
                            <img src="<?= htmlspecialchars($property['image_url']); ?>" alt="<?= htmlspecialchars($property['title']); ?>">
                        <?php else: ?>
                            <img src="/subdisystem/assets/img/property-placeholder.jpg" alt="No image">
                        <?php endif; ?>
                    </div>
                    <div class="property-details">
                        <h3 class="property-title"><?= htmlspecialchars($property['title']); ?></h3>
                        <p class="property-description"><?= htmlspecialchars($property['description']); ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="property-price">₱<?= number_format($property['price'], 2); ?></div>
                            <div class="property-type"><?= ucfirst(htmlspecialchars($property['type'])); ?></div>
                        </div>
                        
                        <?php
                            $statusClass = 'available';
                            switch(strtolower($property['status'])) {
                                case 'sold':
                                    $statusClass = 'sold';
                                    $statusIcon = 'bi-tag-fill';
                                    break;
                                case 'pending':
                                    $statusClass = 'pending';
                                    $statusIcon = 'bi-clock-fill';
                                    break;
                                case 'for rent':
                                    $statusClass = 'for-rent';
                                    $statusIcon = 'bi-house-door-fill';
                                    break;
                                default:
                                    $statusClass = 'available';
                                    $statusIcon = 'bi-check-circle-fill';
                                    break;
                            }
                        ?>
                        
                        <div class="property-status <?= $statusClass ?>">
                            <i class="bi <?= $statusIcon ?>"></i>
                            <?= ucfirst(htmlspecialchars($property['status'])); ?>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="edit_property.php?id=<?= $property['property_id']; ?>" class="btn-action btn-edit">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <a href="delete_property.php?id=<?= $property['property_id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this property?');" 
                               class="btn-action btn-delete">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
