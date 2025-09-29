<?php
// Include the appropriate header based on user role
include './includes/header.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: /subdisystem/user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user status from the database
$query = "SELECT status FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if the user is inactive
if ($user && $user['status'] === 'inactive') {
    // Redirect with an error message
    $_SESSION['error'] = "Your account has been deactivated. Please contact the administrator.";
    header("Location: /subdisystem/user/login.php");
    exit();
}

// Function to retrieve the facilities
function getFacilities() {
    global $conn;
    $query = "SELECT * FROM amenities";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$facilities = getFacilities();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Facilities</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="facility-gallery-container">
    <div class="facility-header">
        <h1>Our Premium Facilities</h1>
        <p class="facility-subtitle">Explore our range of amenities designed for your comfort and convenience</p>
    </div>
    
    <div class="facility-filter">
        <button class="filter-btn active - 1" data-filter="all">All</button>
        <button class="filter-btn" data-filter="available">Available</button>
        <button class="filter-btn" data-filter="unavailable">Unavailable</button>
    </div>
    
    <div class="facility-grid">
        <?php foreach ($facilities as $facility): 
            $statusClass = strtolower($facility['availability_status']) === 'available' ? 'status-available' : 'status-unavailable';
        ?>
            <div class="facility-item <?= $statusClass ?>" onclick="openModal('modal-<?= $facility['facility_id']; ?>')">
                <div class="facility-image-wrapper">
                    <img src="<?= '/subdisystem/admin/image_faci/' . basename($facility['image_url']); ?>" 
                         alt="<?= htmlspecialchars($facility['name']); ?>" 
                         class="facility-image">
                    <div class="facility-status-badge <?= $statusClass ?>">
                        <?= ucfirst(htmlspecialchars($facility['availability_status'])); ?>
                    </div>
                </div>
                <div class="facility-content">
                    <h3 class="facility-name"><?= htmlspecialchars($facility['name']); ?></h3>
                    <p class="facility-description"><?= htmlspecialchars(substr($facility['description'], 0, 100) . (strlen($facility['description']) > 100 ? '...' : '')); ?></p>
                    <button class="view-details-btn">View Details <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            
            <!-- Modal for Viewing the Image -->
            <div id="modal-<?= $facility['facility_id']; ?>" class="facility-modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('modal-<?= $facility['facility_id']; ?>')">&times;</span>
                    <div class="modal-flex">
                        <div class="modal-image-container">
                            <img src="<?= '/subdisystem/admin/image_faci/' . basename($facility['image_url']); ?>" 
                                alt="<?= htmlspecialchars($facility['name']); ?>" 
                                class="modal-image">
                        </div>
                        <div class="modal-details">
                            <h2><?= htmlspecialchars($facility['name']); ?></h2>
                            <div class="modal-status <?= $statusClass ?>">
                                <i class="fas <?= strtolower($facility['availability_status']) === 'available' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                <?= ucfirst(htmlspecialchars($facility['availability_status'])); ?>
                            </div>
                            <div class="modal-description">
                                <p><?= nl2br(htmlspecialchars($facility['description'])); ?></p>
                            </div>
                            <?php if (strtolower($facility['availability_status']) === 'available'): ?>
                                <a href="/subdisystem/booking/create_booking.php" class="book-now-btn">Book Now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="no-results" style="display: none;">
        <i class="fas fa-search"></i>
        <h3>No facilities found</h3>
        <p>Please try another filter option</p>
    </div>
</div>

<script>
// Filter functionality
document.querySelectorAll('.filter-btn').forEach(button => {
    button.addEventListener('click', function() {
        // Remove active class from all buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to clicked button
        this.classList.add('active');
        
        const filterValue = this.getAttribute('data-filter');
        const facilityItems = document.querySelectorAll('.facility-item');
        let visibleCount = 0;
        
        facilityItems.forEach(item => {
            if (filterValue === 'all') {
                item.style.display = 'block';
                visibleCount++;
            } else if (filterValue === 'available' && item.classList.contains('status-available')) {
                item.style.display = 'block';
                visibleCount++;
            } else if (filterValue === 'unavailable' && item.classList.contains('status-unavailable')) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        // Show "no results" message if no items are visible
        document.querySelector('.no-results').style.display = visibleCount === 0 ? 'flex' : 'none';
    });
});

// Open Modal with Smooth Transition
function openModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = "flex";
    setTimeout(() => {
        modal.classList.add("modal-active");
    }, 10);
    document.body.classList.add("modal-open");
}

// Close Modal with Smooth Transition
function closeModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.classList.remove("modal-active");
    setTimeout(() => {
        modal.style.display = "none";
    }, 300);
    document.body.classList.remove("modal-open");
}

// Close modal when clicking outside of modal content
window.onclick = function(event) {
    document.querySelectorAll(".facility-modal").forEach(modal => {
        if (event.target === modal) {
            closeModal(modal.id);
        }
    });
};

// Book Now Button (placeholder functionality)
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('book-now-btn')) {
        alert('Booking functionality would go here.');
        e.stopPropagation(); // Prevent modal from closing
    }
});

document.addEventListener("click", function (event) {
            if (event.target.matches(".dropdown-toggle")) {
                let dropdown = new bootstrap.Dropdown(event.target);
                dropdown.show();
            }
        });

        console.log("Bootstrap version:", bootstrap?.Dropdown ? "Loaded" : "Not Loaded");
</script>


<?php include './includes/footer.php'; ?>
</body>
</html>


<style>
    
/* Global Styles */

/* Facility Gallery Container */
.facility-gallery-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* Header Section */
.facility-header {
    text-align: center;
    margin-bottom: 40px;
}

.facility-header h1 {
    font-size: 2.5rem;
    color:rgb(0, 0, 0);
    margin-bottom: 10px;
    font-weight: 700;
}

.facility-subtitle {
    font-size: 1.1rem;
    color:black;
    max-width: 700px;
    margin: 0 auto;
}

/* Filter Buttons */
.facility-filter {
    display: flex;
    justify-content: center;
    margin-bottom: 30px;
    gap: 12px;
 
}

.filter-btn {
    background: #f1f1f1;
    border:solid;
    padding: 10px 20px;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    color: #555;
}

.filter-btn:hover {
    background: #e0e0e0;
}

.filter-btn.active {
    background: #3498db;
    color: white;
}

/* Facility Grid */
.facility-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
}

/* Facility Item */
.facility-item {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
    position: relative;
    display: flex;
    flex-direction: column;
}

.facility-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

/* Facility Image */
.facility-image-wrapper {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.facility-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.facility-item:hover .facility-image {
    transform: scale(1.05);
}

/* Status Badge */
.facility-status-badge {
 
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-available {
    background-color: rgba(255, 255, 255, 0.9);
    color: black;
}

.status-unavailable {
    background-color: rgba(137, 137, 137, 0.9);
    color: white;
}

/* Facility Content */
.facility-content {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.facility-name {
    font-size: 1.3rem;
    color: #2c3e50;
    margin-bottom: 10px;
    font-weight: 600;
}

.facility-description {
    color:black;
    margin-bottom: 20px;
    flex-grow: 1;
}

.view-details-btn {
    background: none;
    border: none;
    color: #3498db;
    font-weight: 600;
    padding: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    align-self: flex-start;
    transition: color 0.3s;
}

.view-details-btn:hover {
    color: #2980b9;
}

.view-details-btn i {
    transition: transform 0.3s;
}

.view-details-btn:hover i {
    transform: translateX(5px);
}

/* Modal Styles */
.facility-modal {
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
    transition: opacity 0.3s ease;
}

.modal-active {
    opacity: 1;
}

.modal-content {
    background: white;
    width: 90%;
    max-width: 900px;
    border-radius: 12px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    transform: scale(0.95);
    transition: transform 0.3s ease;
}

.modal-active .modal-content {
    transform: scale(1);
}

.close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    color: white;
    cursor: pointer;
    z-index: 10;
    text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
    transition: transform 0.3s;
}

.close:hover {
    transform: scale(1.2);
}

.modal-flex {
    display: flex;
    flex-direction: column;
}

.modal-image-container {
    width: 100%;
    height: 300px;
}

.modal-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.modal-details {
    padding: 30px;
}

.modal-details h2 {
    font-size: 1.8rem;
    color:rgb(255, 255, 255);
    margin-bottom: 15px;
}

.modal-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 15px;
    border-radius: 30px;
    font-weight: 600;
    margin-bottom: 20px;
}

.modal-description {
    margin-bottom: 25px;
    color: #555;
    line-height: 1.7;
}

.book-now-btn {
    background: #3498db;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 30px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}

.book-now-btn:hover {
    background: #2980b9;
}

/* No Results */
.no-results {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 50px 20px;
    color: #7f8c8d;
}

.no-results i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #bdc3c7;
}

/* Modal Open Body */
body.modal-open {
    overflow: hidden;
}

/* Responsive Adjustments */
@media (min-width: 768px) {
    .modal-flex {
        flex-direction: row;
    }
    
    .modal-image-container {
        width: 50%;
        height: auto;
    }
    
    .modal-details {
        width: 50%;
    }
}

@media (max-width: 768px) {
    .facility-header h1 {
        font-size: 2rem;
    }
    
    .facility-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}

@media (max-width: 480px) {
    .facility-filter {
        flex-wrap: wrap;
    }
}

</style>
