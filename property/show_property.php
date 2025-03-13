<?php
include '../includes/header.php';

// Fetch available properties
$query = "SELECT * FROM properties WHERE status = 'available' OR status = 'for rent'";
$result = $conn->query($query);
$properties = $result->fetch_all(MYSQLI_ASSOC);

// Group properties by type
$propertyTypes = [];
foreach ($properties as $property) {
    if (!isset($propertyTypes[$property['type']])) {
        $propertyTypes[$property['type']] = [];
    }
    $propertyTypes[$property['type']][] = $property;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Properties For Sale</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <div class="container property-container">
        <div class="property-filters">
            <div class="filter-header">
                <h2>Available Properties</h2>
                <span class="property-count"><?= count($properties) ?> listings</span>
            </div>
            
            <div class="filter-options">
                <div class="filter-group">
                    <label>Property Type</label>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn filter-btn active" data-filter="all">All</button>
                        <?php foreach (array_keys($propertyTypes) as $type): ?>
                            <button type="button" class="btn filter-btn" data-filter="<?= $type ?>"><?= ucfirst($type) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="filter-group">
                    <label>Sort By</label>
                    <select id="sort-properties" class="form-select">
                        <option value="price-low">Price: Low to High</option>
                        <option value="price-high">Price: High to Low</option>
                        <option value="newest">Newest First</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="property-grid">
            <?php foreach ($properties as $property): ?>
                <div class="property-card" data-type="<?= $property['type'] ?>" data-price="<?= $property['price'] ?>">
                    <div class="property-badge"><?= ucfirst($property['type']) ?></div>
                    <div class="property-favorite"><i class="far fa-heart"></i></div>
                    <div class="property-image">
                        <img src="<?= htmlspecialchars($property['image_url']); ?>" alt="<?= htmlspecialchars($property['title']); ?>">
                    </div>
                    <div class="property-details">
                        <h3 class="property-title"><?= htmlspecialchars($property['title']); ?></h3>
                        <div class="property-price">$<?= number_format($property['price'], 0); ?></div>
                        <div class="property-actions">
                            <button class="btn-view-details" onclick="openModal('modal-<?= $property['property_id']; ?>')">View Details</button>
                        </div>
                    </div>
                </div>
                
                <!-- Modal for detailed view -->
                <div id="modal-<?= $property['property_id']; ?>" class="property-modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeModal('modal-<?= $property['property_id']; ?>')"><i class="fas fa-times"></i></span>
                        
                        <div class="modal-gallery">
                            <div class="main-image">
                                <img src="<?= htmlspecialchars($property['image_url']); ?>" alt="<?= htmlspecialchars($property['title']); ?>">
                            </div>
                            <div class="thumbnail-strip">
                                <div class="thumbnail active">
                                    <img src="<?= htmlspecialchars($property['image_url']); ?>" alt="Main view">
                                </div>
                                <!-- Placeholder thumbnails - in a real app, you'd have multiple images per property -->
                                <div class="thumbnail">
                                    <img src="<?= htmlspecialchars($property['image_url']); ?>" alt="Other view">
                                </div>
                                <div class="thumbnail">
                                    <img src="<?= htmlspecialchars($property['image_url']); ?>" alt="Other view">
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-info">
                            <div class="modal-header">
                                <div>
                                    <h2 class="modal-title"><?= htmlspecialchars($property['title']); ?></h2>
                                </div>
                                <div class="modal-price">$<?= number_format($property['price'], 0); ?></div>
                            </div>
                            
                           
                            
                            <div class="modal-section">
                                <h3>Property Description</h3>
                                <p><?= htmlspecialchars($property['description']); ?></p>
                            </div>
                            
                            
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="no-results" style="display: none;">
            <i class="fas fa-home"></i>
            <h3>No properties found</h3>
            <p>Try adjusting your filters to find more properties</p>
        </div>
    </div>

    <script>
    // Property filtering
    $(document).ready(function() {
        // Filter by property type
        $('.filter-btn').click(function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            
            const filterValue = $(this).data('filter');
            let visibleCount = 0;
            
            $('.property-card').each(function() {
                if (filterValue === 'all' || $(this).data('type') === filterValue) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });
            
            // Show no results message if needed
            if (visibleCount === 0) {
                $('.no-results').show();
            } else {
                $('.no-results').hide();
            }
        });
        
        
        // Sort properties
        $('#sort-properties').change(function() {
            const sortValue = $(this).val();
            const $grid = $('.property-grid');
            
            let $items = $('.property-card').get();
            
            $items.sort(function(a, b) {
                const priceA = $(a).data('price');
                const priceB = $(b).data('price');
                
                if (sortValue === 'price-low') {
                    return priceA - priceB;
                } else if (sortValue === 'price-high') {
                    return priceB - priceA;
                } else {
                    // For 'newest' - in a real app, you'd use a date property
                    return $(b).index() - $(a).index();
                }
            });
            
            $.each($items, function(index, item) {
                $grid.append(item);
            });
        });
        
        // Favorite toggle
        $('.property-favorite').click(function(e) {
            e.stopPropagation();
            $(this).find('i').toggleClass('far fas');
        });
    });

    // Open Modal Function
    function openModal(modalId) {
        document.getElementById(modalId).style.display = "flex";
        document.body.style.overflow = "hidden"; // Prevent scrolling when modal is open
        
        // Fade in animation
        setTimeout(function() {
            document.getElementById(modalId).classList.add('show');
        }, 10);
    }
    
    // Close Modal Function
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
        
        // Wait for animation to complete before hiding
        setTimeout(function() {
            document.getElementById(modalId).style.display = "none";
            document.body.style.overflow = "auto"; // Restore scrolling
        }, 300);
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('property-modal')) {
            closeModal(event.target.id);
        }
    };
    </script>

    <style>
    /* Global Styles */
    body {
        background-color:white;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
        line-height: 1.6;
    }
    
    h1, h2, h3, h4, h5, h6 {
        font-weight: 600;
    }
    
    /* Hero Section */
    .property-hero {
        background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('https://via.placeholder.com/1920x600');
        background-size: cover;
        background-position: center;
        height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: white;
        margin-bottom: 40px;
    }
    
    .hero-content {
        max-width: 800px;
        padding: 0 20px;
    }
    
    .hero-content h1 {
        font-size: 3rem;
        margin-bottom: 15px;
        text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
    }
    
    .hero-content p {
        font-size: 1.2rem;
        margin-bottom: 30px;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
    }
    
    

    /* Property Container */
    .property-container {
        max-width: 1200px;
        margin: 0 auto 60px;
    }
    
    /* Property Filters */
    .property-filters {
        margin-bottom: 30px;
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .filter-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    
    .filter-header h2 {
        margin: 0;
        font-size: 1.8rem;
        color: #2c3e50;
    }
    
    .property-count {
        background: #f1f1f1;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 14px;
        color: #555;
    }
    
    .filter-options {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: flex-end;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-group label {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
    }
    
    .filter-btn {
        background: #f1f1f1;
        color: #555;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        transition: all 0.3s;
    }
    
    .filter-btn:hover {
        background: #e0e0e0;
    }
    
    .filter-btn.active {
        background: #3498db;
        color: white;
    }
    
    /* Property Grid */
    .property-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 30px;
    }
    
    /* Property Card */
    .property-card {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
    }
    
    .property-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
    }
    
    .property-badge {
        position: absolute;
        top: 15px;
        left: 15px;
        background: #3498db;
        color: white;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 20px;
        z-index: 1;
    }
    
    .property-favorite {
        position: absolute;
        top: 15px;
        right: 15px;
        background: white;
        color: #777;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 1;
        transition: background 0.3s, color 0.3s, transform 0.3s;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    .property-favorite:hover {
        transform: scale(1.1);
    }
    
    .property-favorite .fas {
        color: #e74c3c;
    }
    
    .property-image {
        height: 220px;
        position: relative;
    }
    
    .property-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }
    
    .property-card:hover .property-image img {
        transform: scale(1.05);
    }
    
    .property-details {
        padding: 20px;
    }
    
    .property-title {
        font-size: 1.3rem;
        margin: 0 0 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .property-price {
        font-size: 1.4rem;
        font-weight: 700;
        color: #3498db;
        margin-bottom: 15px;
    }
    
    
    .property-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn-view-details, .btn-contact-agent {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.3s;
    }
    
    .btn-view-details {
        background: #3498db;
        color: white;
    }
    
    .btn-view-details:hover {
        background: #2980b9;
    }
    
    .btn-contact-agent {
        background: #f1f1f1;
        color: #555;
    }
    
    .btn-contact-agent:hover {
        background: #e0e0e0;
    }
    
    /* No Results */
    .no-results {
        text-align: center;
        padding: 50px 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .no-results i {
        font-size: 48px;
        color: #bdc3c7;
        margin-bottom: 15px;
    }
    
    .no-results h3 {
        font-size: 1.4rem;
        margin-bottom: 10px;
        color: #2c3e50;
    }
    
    .no-results p {
        color: #7f8c8d;
    }
    
    /* Modal Styles */
    .property-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .property-modal.show {
        opacity: 1;
    }
    
    .modal-content {
        display: flex;
        flex-direction: column;
        background: white;
        width: 90%;
        max-width: 1000px;
        max-height: 90vh;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
        transform: scale(0.95);
        transition: transform 0.3s;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }
    
    .property-modal.show .modal-content {
        transform: scale(1);
    }
    
    .close {
        position: absolute;
        top: 15px;
        right: 15px;
        background: rgba(0, 0, 0, 0.5);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 2;
        transition: background 0.3s, transform 0.3s;
    }
    
    .close:hover {
        background: rgba(0, 0, 0, 0.7);
        transform: rotate(90deg);
    }
    
    .modal-gallery {
        background: #f8f9fa;
    }
    
    .main-image {
        height: 400px;
        overflow: hidden;
    }
    
    .main-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .thumbnail-strip {
        display: flex;
        padding: 10px;
        gap: 10px;
        background: white;
    }
    
    .thumbnail {
        width: 80px;
        height: 60px;
        border-radius: 5px;
        overflow: hidden;
        cursor: pointer;
        opacity: 0.7;
        transition: opacity 0.3s;
    }
    
    .thumbnail:hover, .thumbnail.active {
        opacity: 1;
    }
    
    .thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .modal-info {
        padding: 30px;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }
    
    .modal-title {
        font-size: 1.8rem;
        margin: 0 0 10px;
        color: #2c3e50;
    }
    
    .modal-location {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #7f8c8d;
        margin: 0;
    }
    
    .modal-price {
        font-size: 1.8rem;
        font-weight: 700;
        color: #3498db;
    }
    
    .modal-features {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 30px;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
    }
    
    .feature {
        text-align: center;
        flex: 1;
        min-width: 80px;
    }
    
    .feature i {
        font-size: 24px;
        color: #3498db;
        margin-bottom: 5px;
    }
    
    .feature span {
        display: block;
        font-size: 1.2rem;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .feature p {
        margin: 0;
        color: #7f8c8d;
        font-size: 14px;
    }
    
    .modal-section {
        margin-bottom: 30px;
    }
    
    .modal-section h3 {
        font-size: 1.3rem;
        margin-bottom: 15px;
        color: #2c3e50;
    }
    
    .amenities-list {
        list-style: none;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
    }
    
    .amenities-list li {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .amenities-list i {
        color: #2ecc71;
    }
    
    .modal-actions {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .btn-schedule, .btn-contact {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background 0.3s;
    }
    
    .btn-schedule {
        background: #3498db;
        color: white;
    }
    
    .btn-schedule:hover {
        background: #2980b9;
    }
    
    .btn-contact {
        background: #f1f1f1;
        color: #555;
    }
    
    .btn-contact:hover {
        background: #e0e0e0;
    }
    
    .modal-agent {
        display: flex;
        gap: 20px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 10px;
    }
    
    .agent-photo {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        overflow: hidden;
    }
    
    .agent-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .agent-info {
        flex: 1;
    }
    
    .agent-info h4 {
        margin: 0 0 5px;
        font-size: 1.1rem;
        color: #2c3e50;
    }
    
    .agent-info p {
        margin: 0 0 5px;
        color: #7f8c8d;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 992px) {
        .property-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .modal-content {
            max-width: 90%;
        }
    }
    
    @media (max-width: 768px) {
        .hero-content h1 {
            font-size: 2.2rem;
        }
        
        .property-hero {
            height: 300px;
        }
        
        .filter-options {
            flex-direction: column;
            gap: 15px;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .modal-features {
            gap: 10px;
        }
        
        .modal-actions {
            flex-direction: column;
        }
        
        .main-image {
            height: 300px;
        }
    }
    
    @media (max-width: 576px) {
        .property-grid {
            grid-template-columns: 1fr;
        }
        
        .property-card {
            max-width: 100%;
        }
        
        .hero-content h1 {
            font-size: 1.8rem;
        }
        
        .hero-content p {
            font-size: 1rem;
        }
    }