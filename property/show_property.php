<?php
include '../includes/header.php';

// Fetch available properties with user (agent) information
$query = "SELECT p.*, GROUP_CONCAT(pi.image_url) AS images, u.f_name, u.l_name, u.email, u.phone_number, u.image_url as user_image, u.role 
          FROM properties p 
          LEFT JOIN property_images pi ON p.property_id = pi.property_id 
          JOIN users u ON p.user_id = u.user_id 
          WHERE p.status = 'available' OR p.status = 'for rent' 
          GROUP BY p.property_id";
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
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   


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
                        <?php 
                        $images = explode(',', $property['images']);
                        foreach ($images as $index => $image): 
                            if ($index === 0): ?>
                                <img src="<?= htmlspecialchars($image); ?>" alt="<?= htmlspecialchars($property['title']); ?>">
                            <?php endif; 
                        endforeach; ?>
                    </div>
                    <div class="property-details">
                        <h3 class="property-title"><?= htmlspecialchars($property['title']); ?></h3>
                        <div class="property-price">₱<?= number_format($property['price'], 0); ?></div>
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
                                <img src="<?= htmlspecialchars($images[0]); ?>" alt="<?= htmlspecialchars($property['title']); ?>">
                            </div>
                            <div class="thumbnail-strip">
                                <?php foreach ($images as $image): ?>
                                    <div class="thumbnail">
                                        <img src="<?= htmlspecialchars($image); ?>" alt="Thumbnail">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="modal-info">
                            <div class="modal-header">
                                <div>
                                    <h2 class="modal-title"><?= htmlspecialchars($property['title']); ?></h2>
                                </div>
                                <div class="modal-price">₱<?= number_format($property['price'], 0); ?></div>
                            </div>
                            
                            <div class="modal-section">
                                <h3>Property Description</h3>
                                <p><?= htmlspecialchars($property['description']); ?></p>
                            </div>
                            
                            <!-- Agent Information Section -->
                            <div class="modal-section">
                                <h3>Property Agent</h3>
                                <div class="modal-agent">
                                    <div class="agent-photo">
                                        <?php if (!empty($property['user_image'])): ?>
                                            <img src="<?= htmlspecialchars($property['user_image']); ?>" alt="Agent Photo">
                                        <?php else: ?>
                                            <img src="/subdisystem/assets/img/default-profile.jpg" alt="Agent Photo">
                                        <?php endif; ?>
                                    </div>
                                    <div class="agent-info">
                                        <h4><?= htmlspecialchars($property['f_name'] . ' ' . $property['l_name']); ?></h4>
                                        
                                        <div class="agent-role">
                                            <span class="badge badge-<?= $property['role'] ?>">
                                                <?= ucfirst(htmlspecialchars($property['role'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="agent-contact-info">
                                            <?php if (!empty($property['phone_number'])): ?>
                                                <p><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($property['phone_number']); ?></p>
                                            <?php endif; ?>
                                            <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($property['email']); ?></p>
                                        </div>
                                        

                                    </div>
                                </div>
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
        
        <!-- Add this fullscreen view container after the property-grid div -->
        <div class="fullscreen-view">
            <span class="fullscreen-close"><i class="fas fa-times"></i></span>
            <img src="" alt="Fullscreen property view" class="fullscreen-image">
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
        
        // Add image fullscreen view functionality
        $('.main-image').click(function() {
            const imgSrc = $(this).find('img').attr('src');
            $('.fullscreen-image').attr('src', imgSrc);
            $('.fullscreen-view').css('display', 'flex');
            $('body').css('overflow', 'hidden');
        });
        
        $('.fullscreen-close').click(function() {
            $('.fullscreen-view').css('display', 'none');
            $('body').css('overflow', 'auto');
        });
        
        // Close fullscreen on clicking outside the image
        $('.fullscreen-view').click(function(e) {
            if (e.target !== $('.fullscreen-image')[0]) {
                $('.fullscreen-view').css('display', 'none');
                $('body').css('overflow', 'auto');
            }
        });
        
        // Thumbnail click handler to update main image
        $('.thumbnail').click(function() {
            const imgSrc = $(this).find('img').attr('src');
            $(this).closest('.modal-content').find('.main-image img').attr('src', imgSrc);
            $('.thumbnail').removeClass('active');
            $(this).addClass('active');
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

    // Message Agent Function
    $(document).on('click', '.agent-contact-btn.message', function(e) {
        e.preventDefault();
        const agentId = $(this).data('agent');
        
        // Check if user is logged in
        <?php if (isset($_SESSION['user_id'])): ?>
            // Here you can implement AJAX to send message or redirect to a messaging page
            alert("Messaging feature will be implemented soon. Agent ID: " + agentId);
        <?php else: ?>
            alert("Please log in to message an agent");
            window.location.href = "/subdisystem/login.php";
        <?php endif; ?>
    });
    </script>
</body>
</html>

<style>
    h1, h2, h3, h4, h5, h6 {
    font-weight: 700;
    letter-spacing: -0.02em;
}

/* Hero Section */
.property-hero {
    background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://via.placeholder.com/1920x600');
    background-size: cover;
    background-position: center;
    height: 450px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: white;
    margin-bottom: 60px;
    position: relative;
    overflow: hidden;
}

.hero-content {
    max-width: 900px;
    padding: 0 30px;
    position: relative;
    z-index: 2;
}

.hero-content h1 {
    font-size: 3.5rem;
    margin-bottom: 20px;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.6);
    animation: fadeInUp 1s ease;
}

.hero-content p {
    font-size: 1.4rem;
    margin-bottom: 35px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.6);
    opacity: 0.9;
    animation: fadeInUp 1.2s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enlarged Property Container */
.property-container {
    max-width: 1800px; /* Further increased width for better detail viewing */
    margin: 0 auto 100px;
    padding: 0 30px;
}

/* Enlarged Property Description Scrollbar */
.modal-info {   
    padding: 40px 50px; /* Increased padding */
    overflow-y: auto;
    max-height: 70vh; /* Increased from 55vh */
}

.modal-info::-webkit-scrollbar {
    width: 14px; /* Larger scrollbar width */
}

.modal-info::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.modal-info::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
    border: 3px solid #f1f1f1;
}

.modal-info::-webkit-scrollbar-thumb:hover {
    background: #555;
}


/* Property Filters */
.property-filters {
    margin-bottom: 40px;
    background: white;
    padding: 25px 30px;
    border-radius: 12px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.07);
    position: relative;
    z-index: 1;
    transition: all 0.3s ease;
}

.property-filters:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
}

.filter-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.filter-header h2 {
    margin: 0;
    font-size: 2rem;
    color: #1a202c;
    position: relative;
}

.filter-header h2:after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 40px;
    height: 3px;
    background: var(--primary);
    border-radius: 2px;
}

.property-count {
    background: #f0f5ff;
    padding: 8px 18px;
    border-radius: 25px;
    font-size: 15px;
    color: var(--primary);
    font-weight: 600;
    letter-spacing: 0.03em;
}

.filter-options {
    display: flex;
    flex-wrap: wrap;
    gap: 25px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 10px;
    color: #4a5568;
    font-weight: 600;
    font-size: 0.95rem;
}

.filter-btn {
    background: #f7fafc;
    color: #4a5568;
    border: 1px solid rgba(0, 0, 0, 0.08);
    padding: 10px 18px;
    border-radius: 8px;
    transition: all 0.3s;
    font-weight: 500;
}

.filter-btn:hover {
    background: #edf2f7;
    transform: translateY(-2px);
}

.filter-btn.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 12px rgba(66, 133, 244, 0.2);
    border-color: var(--primary);
}

/* Property Grid */
.property-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 35px;
}

/* Property Card */
.property-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    transition: transform 0.4s, box-shadow 0.4s;
    position: relative;
    border: 1px solid rgba(0, 0, 0, 0.05);
    transform-origin: center bottom;
}

.property-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.property-badge {
    position: absolute;
    top: 20px;
    left: 20px;
    background: var(--primary);
    color: white;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 700;
    border-radius: 30px;
    z-index: 1;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.property-favorite {
    position: absolute;
    top: 20px;
    right: 20px;
    background: white;
    color: #64748b;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1;
    transition: all 0.3s;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.property-favorite:hover {
    transform: scale(1.15);
    background: #fff5f5;
}

.property-favorite .fas {
    color: #e53e3e;
}

/* Property Card - Enlarged property image */
.property-image {
    height: 300px; /* Increased from 240px for larger property thumbnails */
    position: relative;
    overflow: hidden;
}

.property-image:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 60px;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.3), transparent);
}

.property-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.property-card:hover .property-image img {
    transform: scale(1.08);
}

.property-details {
    padding: 25px;
}

.property-title {
    font-size: 1.4rem;
    margin: 0 0 15px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #1a202c;
    line-height: 1.3;
}

.property-price {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 20px;
    display: flex;
    align-items: baseline;
}

.property-price::before {
    font-size: 1rem;
    margin-right: 2px;
    font-weight: 600;
    opacity: 0.8;
}

.property-actions {
    display: flex;
    gap: 12px;
}

.btn-view-details, .btn-contact-agent {
    flex: 1;
    padding: 12px 10px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 700;
    transition: all 0.3s;
    font-size: 0.95rem;
}

.btn-view-details {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 12px rgba(66, 133, 244, 0.25);
}

.btn-view-details:hover {
    background: var(--primary-dark);
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(66, 133, 244, 0.3);
}

.btn-contact-agent {
    background: #f7fafc;
    color: #4a5568;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.btn-contact-agent:hover {
    background: #edf2f7;
    transform: translateY(-3px);
}

/* No Results */
.no-results {
    text-align: center;
    padding: 70px 30px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.07);
}

.no-results i {
    font-size: 60px;
    color: #cbd5e0;
    margin-bottom: 20px;
}

.no-results h3 {
    font-size: 1.6rem;
    margin-bottom: 15px;
    color: #1a202c;
}

.no-results p {
    color: #718096;
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto;
}

/* Modal Styles */
.property-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    opacity: 0;
    transition: opacity 0.4s ease;
    backdrop-filter: blur(5px);
}

.property-modal.show {
    opacity: 1;
}

.modal-content {
    display: flex;
    flex-direction: row-reverse; /* Image on the right */
    background: white;
    width: 95%;
    max-width: 1400px; /* Increased from 1100px */
    max-height: 95vh; /* Increased from 90vh */
    border-radius: 15px;
    overflow: hidden;
    position: relative;
    transform: scale(0.95);
    transition: transform 0.4s cubic-bezier(.17,.67,.83,.67);
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
}

.property-modal.show .modal-content {
    transform: scale(1);
}

.close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 2;
    transition: all 0.3s;
    font-size: 1.2rem;
}

.close:hover {
    background: rgba(0, 0, 0, 0.8);
    transform: rotate(90deg);
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
}

/* Modal Gallery - Significantly enlarged property images */
.modal-gallery {
    background: rgb(255, 255, 255);
    position: relative;
    flex: 1.5; /* Increased from 1.2 to make the image section larger */
    display: flex;
    flex-direction: column;
}

.main-image {
    height: 650px; /* Significantly increased from 550px */
    width: 100%;
    overflow: hidden;
    position: relative;
    cursor: zoom-in;
}

.main-image::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 100px;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.4), transparent);
}

.main-image img {
    width: 100%;
    height: 100%;
    object-fit: contain; /* Ensures the whole image is visible */
    transition: transform 0.5s ease;
}

/* Add a zoom effect on hover */
.main-image:hover img {
    transform: scale(1.05); /* Slightly larger zoom on hover */
}

/* Add a "Click to zoom" hint */
.main-image::before {
    content: 'Click to view full-size';
    position: absolute;
    bottom: 15px;
    right: 15px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.85rem;
    z-index: 2;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.main-image:hover::before {
    opacity: 1;
}

.thumbnail-strip {
    display: flex;
    padding: 15px;
    gap: 15px;
    background: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    overflow-x: auto;
}

.thumbnail {
    width: 140px; /* Increased from 120px */
    height: 90px; /* Increased from 80px */
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    opacity: 0.7;
    transition: all 0.3s;
    border: 2px solid transparent;
    flex-shrink: 0;
}

.thumbnail:hover {
    opacity: 1;
    transform: translateY(-3px);
}

.thumbnail.active {
    opacity: 1;
    border-color: var(--primary);
    box-shadow: 0 3px 10px rgba(66, 133, 244, 0.2);
}

.thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Fullscreen View - Enhanced for better image viewing */
.fullscreen-view {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.97); /* Darker background for better contrast */
    z-index: 2000;
    display: none;
    justify-content: center;
    align-items: center;
}

.fullscreen-image {
    max-width: 98%; /* Increased from 95% */
    max-height: 98%; /* Increased from 95% */
    object-fit: contain;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
}

/* Keep agent photo size the same - explicitly define to ensure no changes */
.agent-photo {
    width: 140px; /* Keep the existing size */
    height: 140px; /* Keep the existing size */
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    position: relative;
    flex-shrink: 0;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .modal-content {
        flex-direction: column; /* Stack content at smaller sizes */
    }
    
    .modal-gallery {
        width: 100%;
    }
    
    .main-image {
        height: 550px; /* Adjust for medium screens, but still larger than before */
    }
}

@media (max-width: 768px) {
    .property-image {
        height: 280px; /* Still larger than original on mobile */
    }
    
    .main-image {
        height: 450px; /* Adjust for smaller screens, but still larger than before */
    }
}

@media (max-width: 576px) {
    .property-image {
        height: 250px; /* Maintain larger size even on small screens */
    }
    
    .main-image {
        height: 380px;
    }
    
    .thumbnail {
        width: 110px;
        height: 75px;
    }
}

/* Image controls for navigation */
.image-controls {
    position: absolute;
    top: 50%;
    width: 100%;
    display: flex;
    justify-content: space-between;
    transform: translateY(-50%);
    padding: 0 15px;
    z-index: 5;
}

.image-control {
    background: rgba(0, 0, 0, 0.5);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}

.image-control:hover {
    background: rgba(0, 0, 0, 0.8);
    transform: scale(1.1);
}

/* Zoom indicator */
.zoom-indicator {
    position: absolute;
    bottom: 15px;
    right: 15px;
    background: rgba(0, 0, 0, 0.5);
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.8rem;
    opacity: 0.7;
    z-index: 1;
}
</style>
<?php include '../includes/footer.php'; ?>