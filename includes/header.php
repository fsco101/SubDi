<?php
session_start();
if (file_exists(__DIR__ . "/config.php")) {
    include __DIR__ . "/config.php";
} elseif (file_exists(__DIR__ . "/../config.php")) {
    include __DIR__ . "/../config.php";
} else {
    die("Error: config.php not found!");
}
// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: /subdisystem/index.php");
    exit();
}

ob_start();






// Get user ID from session
$userId = $_SESSION['user_id'];

// Fetch user's role
$roleQuery = "SELECT role FROM users WHERE user_id = ?";
$stmt = $conn->prepare($roleQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$roleResult = $stmt->get_result()->fetch_assoc();
$userRole = $roleResult['role'] ?? 'resident'; // Default to 'resident' if not found

// Store role in session for later use
$_SESSION['user_role'] = $userRole;

// Set dashboard URL based on role
$dashboardUrl = ($userRole === 'admin') ? '/subdisystem/adminDashboard.php' : '/subdisystem/dashboard.php';

// Fetch user's profile image
$query = "SELECT image_url FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$imageUrl = !empty($user['image_url']) ? '/subdisystem/user/uploads/' . basename($user['image_url']) : '/subdisystem/user/uploads/default_profile.jpg';

// Fetch unread notifications count
$notifQuery = "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE";
$stmt = $conn->prepare($notifQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifResult = $stmt->get_result()->fetch_assoc();
$unreadCount = $notifResult['unread_count'] ?? 0;
?>


<!DOCTYPE html>
<html lang="en">
<head>
 
    <link rel="stylesheet" href="../style/style.css">   
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
   
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subdivision Management System</title>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top shadow">
    <div class="container-fluid px-2 px-md-3">
        <!-- Logo and brand -->
        <a class="navbar-brand d-flex align-items-center" href="<?php echo $dashboardUrl; ?>">
            <img src="/subdisystem/dashboard image/logo.gif" alt="Logo" class="navbar-logo me-2">
            <span class="d-none d-sm-inline">Subdivision Management</span>
            <span class="d-inline d-sm-none">SubM</span>
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Main Navigation -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <!-- Home -->
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3" href="<?php echo $dashboardUrl; ?>">
                        <i class="bi bi-house-door me-1"></i>
                        <span>Home</span>
                    </a>
                </li>
  
                <!-- Facilities -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle rounded-pill px-3" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-building me-1"></i>
                        <span>Facilities</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-animated">
                        <li><a class="dropdown-item" href="/subdisystem/view_faci.php"><i class="bi bi-eye me-2"></i> View Facilities</a></li>
                        <li><a class="dropdown-item" href="/subdisystem/booking/create_booking.php"><i class="bi bi-calendar-plus me-2"></i> Book Facility</a></li>
                        <li><a class="dropdown-item" href="/subdisystem/booking/view_bookings.php"><i class="bi bi-calendar-check me-2"></i> My Bookings</a></li>
                    </ul>
                </li>

                <!-- Rentals -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle rounded-pill px-3" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-box-seam me-1"></i>
                        <span>Rentals</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-animated">
                        <li><a class="dropdown-item" href="/subdisystem/rentals/rent_item.php"><i class="bi bi-list-check me-2"></i> Available Items</a></li>
                        <li><a class="dropdown-item" href="/subdisystem/rentals/my_item.php"><i class="bi bi-box me-2"></i> My Rentals</a></li>
                    </ul>
                </li>

                <!-- Properties -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle rounded-pill px-3" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-house me-1"></i>
                        <span>Properties</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-animated">
                        <li><a class="dropdown-item" href="/subdisystem/property/show_property.php"><i class="bi bi-houses me-2"></i> View Listings</a></li>
                        <li><a class="dropdown-item" href="/subdisystem/property/create_property.php"><i class="bi bi-plus-circle me-2"></i> Add Listing</a></li>
                        <li><a class="dropdown-item" href="/subdisystem/property/index_properties.php"><i class="bi bi-pencil-square me-2"></i> Edit Listings</a></li>
                    </ul>
                </li>

                <!-- Maintenance -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle rounded-pill px-3" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-tools me-1"></i>
                        <span>Maintenance</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-animated">
                        <li><a class="dropdown-item" href="/subdisystem/maintenance/create_request.php"><i class="bi bi-plus-circle me-2"></i> Request Service</a></li>
                        <li><a class="dropdown-item" href="/subdisystem/maintenance/view_requests.php"><i class="bi bi-clipboard-check me-2"></i> My Requests</a></li>
                    </ul>
                </li>

                <!-- Announcements -->
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3" href="/subdisystem/announcements/view_announcement.php">
                        <i class="bi bi-megaphone me-1"></i>
                        <span>News</span>
                    </a>
                </li>

                <!-- Reviews -->
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3" href="/subdisystem/reviews/review.php">
                        <i class="bi bi-star me-1"></i>
                        <span>Reviews</span>
                    </a>
                </li>

                <!-- Admin Panel (visible only for admins) -->
                <?php if ($userRole === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle rounded-pill px-3 admin-nav" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-shield-lock me-1"></i>
                            <span>Admin</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-animated admin-menu">
                            <div class="admin-menu-header px-3 py-2 mb-2">Admin Controls</div>
                            <li><a class="dropdown-item" href="/subdisystem/admin/approve_bookings.php"><i class="bi bi-calendar-check me-2"></i> Bookings</a></li>
                            <li><a class="dropdown-item" href="/subdisystem/admin/approve_rents.php"><i class="bi bi-box-seam me-2"></i> Rentals</a></li>
                            <li><a class="dropdown-item" href="/subdisystem/admin/approve_service.php"><i class="bi bi-tools me-2"></i> Services</a></li>
                            <li><a class="dropdown-item" href="/subdisystem/admin/user_management.php"><i class="bi bi-people me-2"></i> Users</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/subdisystem/admin/view.php"><i class="bi bi-building me-2"></i> Manage Facilities</a></li>
                            <li><a class="dropdown-item" href="/subdisystem/admin/create_faci.php"><i class="bi bi-plus-circle me-2"></i> Add Facility</a></li>
                            <li><a class="dropdown-item" href="/subdisystem/rentals/create_item.php"><i class="bi bi-plus-square me-2"></i> Add Rental Item</a></li>
                            <li><a class="dropdown-item" href="/subdisystem/rentals/index_item.php"><i class="bi bi-list-ul me-2"></i> View Rental Items</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/subdisystem/announcements/create_announcement.php"><i class="bi bi-megaphone me-2"></i> Create Announcement</a></li>
                            <li><a class="dropdown-item" href="/subdisystem/announcements/index_announcement.php"><i class="bi bi-pencil me-2"></i> Edit Announcements</a></li>
                            <li><a class="dropdown-item" href="/subdisystem/reviews/review_all.php"><i class="bi bi-star me-2"></i> All reviews</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- User Actions Area -->
        <div class="d-flex align-items-center">
            <!-- Notifications -->
            <div class="nav-item me-3">
                <a class="nav-link position-relative p-1 notification-bell" href="/subdisystem/notifications/notification.php">
                    <i class="bi bi-bell-fill fs-5"></i>
                    <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: <?= $unreadCount > 0 ? 'block' : 'none'; ?>; font-size: 0.6rem; padding: 0.2rem 0.35rem;">
                        <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                    </span>
                </a>
            </div>

            <!-- Profile -->
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle p-0 d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?= htmlspecialchars($imageUrl); ?>" alt="Profile" class="rounded-circle profile-image" width="36" height="36">
                </a>
                <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                    <div class="px-3 py-2 d-flex align-items-center border-bottom mb-2">
                        <img src="<?= htmlspecialchars($imageUrl); ?>" alt="Profile" class="rounded-circle me-2" width="32" height="32">
                        <div>
                            <div class="fw-bold">My Account</div>
                            <div class="small text-muted"><?= ucfirst($userRole); ?></div>
                        </div>
                    </div>
                    <li><a class="dropdown-item" href="/subdisystem/user/edit_profile.php"><i class="bi bi-person-circle me-2"></i> Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/subdisystem/user/logout.php" onclick="confirmLogout()"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Determine which items to show based on screen size
    function updateNavigation() {
        // This will be handled by CSS, but can be extended with JavaScript for more complex behaviors
    }

    function confirmLogout() {
    if (confirm("Are you sure you want to log out?")) {
        window.location.href = "/subdisystem/user/logout.php";
    }
}

    function fetchUnreadCount() {
        fetch('/subdisystem/notifications/check_unread.php')
            .then(response => response.json())
            .then(data => {
                let notifBadge = document.getElementById('notif-badge');
                if (notifBadge) {
                    notifBadge.style.display = data.unread > 0 ? 'flex' : 'none';
                    notifBadge.innerHTML = data.unread > 9 ? '9+' : data.unread;
                }
            })
            .catch(error => console.error('Error fetching notification count:', error));
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Set active navigation item based on current page
        const currentLocation = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const linkPath = link.getAttribute('href');
            if (linkPath && currentLocation.includes(linkPath) && linkPath !== '/subdisystem/dashboard.php') {
                link.classList.add('active');
                link.style.backgroundColor = 'rgba(255, 255, 255, 0.15)';
                
                // If it's in a dropdown, also highlight parent
                const dropdownParent = link.closest('.dropdown');
                if (dropdownParent) {
                    const parentLink = dropdownParent.querySelector('.dropdown-toggle');
                    if (parentLink) {
                        parentLink.classList.add('active');
                        parentLink.style.backgroundColor = 'rgba(255, 255, 255, 0.15)';
                    }
                }
            } else if (currentLocation === '/subdisystem/dashboard.php' && linkPath === '/subdisystem/dashboard.php') {
                link.classList.add('active');
                link.style.backgroundColor = 'rgba(255, 255, 255, 0.15)';
            }
        });

        // Setup notification handling
        let notifBell = document.querySelector('.nav-link[href="/subdisystem/notifications/notification.php"]');
        if (notifBell) {
            notifBell.addEventListener("click", function() {
                setTimeout(fetchUnreadCount, 2000);
            });
        }
        
        window.addEventListener('resize', updateNavigation);
        updateNavigation();
        setInterval(fetchUnreadCount, 30000); // Check every 30 seconds
        fetchUnreadCount();
    });

                        // Bootstrap click handler
                        document.addEventListener("click", function (event) {
                if (event.target.matches(".dropdown-toggle")) {
                    let dropdown = new bootstrap.Dropdown(event.target);
                    dropdown.show();
                }
            });

            console.log("Bootstrap version:", bootstrap?.Dropdown ? "Loaded" : "Not Loaded");




                            /**
                 * Page Action Handler - Global utility for managing page actions and reloads
                 * Add this to all PHP files to ensure consistent user experience
                 */
                const PageActions = {
                    // Configuration
                    config: {
                        reloadDelay: 800, // Delay in ms before page reload (allows for visual feedback)
                        showLoadingIndicator: true, // Whether to show loading indicator during actions
                        confirmationRequired: true, // Whether to show confirmation dialogs for destructive actions
                    },

                    // Initialize functionality
                    init: function() {
                        this.setupFormSubmissions();
                        this.setupActionButtons();
                        this.setupConfirmationDialogs();
                        
                        // Add loading indicator to body if enabled
                        if (this.config.showLoadingIndicator) {
                            this.createLoadingIndicator();
                        }
                        
                        console.log("PageActions initialized");
                    },
                    
                    // Handle all form submissions
                    setupFormSubmissions: function() {
                        document.querySelectorAll('form').forEach(form => {
                            form.addEventListener('submit', (e) => {
                                // Skip for forms with data-no-reload attribute
                                if (form.hasAttribute('data-no-reload')) return;
                                
                                // For normal forms, show loading indicator after submit
                                if (!e.defaultPrevented) {
                                    this.showLoading();
                                }
                            });
                        });
                    },
                    
                    // Setup action buttons (any button with data-action attribute)
                    setupActionButtons: function() {
                        document.querySelectorAll('[data-action]').forEach(button => {
                            button.addEventListener('click', (e) => {
                                const action = button.dataset.action;
                                
                                // Handle different action types
                                switch (action) {
                                    case 'reload':
                                        this.reloadPage();
                                        break;
                                    case 'back':
                                        history.back();
                                        break;
                                    case 'submit-form':
                                        const formId = button.dataset.formId;
                                        if (formId) {
                                            document.getElementById(formId).submit();
                                        }
                                        break;
                                    default:
                                        // For custom actions, check if there's a reload parameter
                                        if (button.hasAttribute('data-reload')) {
                                            this.reloadPage(button.dataset.message);
                                        }
                                }
                            });
                        });
                    },
                    
                    // Setup confirmation dialogs for any element with data-confirm attribute
                    setupConfirmationDialogs: function() {
                        document.querySelectorAll('[data-confirm]').forEach(element => {
                            element.addEventListener('click', (e) => {
                                if (!this.config.confirmationRequired) return;
                                
                                const message = element.dataset.confirm || 'Are you sure you want to proceed?';
                                
                                if (!confirm(message)) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    return false;
                                } else {
                                    this.showLoading();
                                    
                                    // If there's a reload attribute, reload after delay
                                    if (element.hasAttribute('data-reload')) {
                                        e.preventDefault();
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, this.config.reloadDelay);
                                    }
                                }
                            });
                        });
                    },
                    
                    // Create loading indicator element
                    createLoadingIndicator: function() {
                        const loadingEl = document.createElement('div');
                        loadingEl.id = 'page-loading-indicator';
                        loadingEl.innerHTML = `
                            <div class="loading-overlay">
                                <div class="loading-spinner"></div>
                                <div class="loading-message">Processing...</div>
                            </div>
                        `;
                        loadingEl.style.cssText = `
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background-color: rgba(0, 0, 0, 0.5);
                            display: none;
                            justify-content: center;
                            align-items: center;
                            z-index: 9999;
                        `;
                        
                        const spinner = document.createElement('style');
                        spinner.textContent = `
                            .loading-overlay {
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                padding: 20px;
                                background-color: white;
                                border-radius: 8px;
                                box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
                            }
                            .loading-spinner {
                                width: 40px;
                                height: 40px;
                                border: 4px solid #f3f3f3;
                                border-top: 4px solid #3498db;
                                border-radius: 50%;
                                animation: spin 1s linear infinite;
                                margin-bottom: 10px;
                            }
                            .loading-message {
                                color: #333;
                                font-family: sans-serif;
                            }
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                        `;
                        
                        document.head.appendChild(spinner);
                        document.body.appendChild(loadingEl);
                    },
                    
                    // Show loading indicator
                    showLoading: function(message = "Processing...") {
                        if (!this.config.showLoadingIndicator) return;
                        
                        const loader = document.getElementById('page-loading-indicator');
                        if (loader) {
                            const messageEl = loader.querySelector('.loading-message');
                            if (messageEl) messageEl.textContent = message;
                            
                            loader.style.display = 'flex';
                        }
                    },
                    
                    // Hide loading indicator
                    hideLoading: function() {
                        if (!this.config.showLoadingIndicator) return;
                        
                        const loader = document.getElementById('page-loading-indicator');
                        if (loader) {
                            loader.style.display = 'none';
                        }
                    },
                    
                    // Reload the page with optional message
                    reloadPage: function(message = null) {
                        if (message) this.showLoading(message);
                        else this.showLoading("Reloading page...");
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, this.config.reloadDelay);
                    }
                };

                // Initialize when DOM is ready
                document.addEventListener('DOMContentLoaded', () => {
                    PageActions.init();
                });

                // Add utility for any action buttons added dynamically 
                window.reloadPage = function() {
                    PageActions.reloadPage();
                };
</script>

</body>
</html>

<style>
        :root {
        --primary-color: #1a73e8;
        --secondary-color: rgb(63, 92, 121);
        --accent-color: #ff6b6b;
        --text-dark: #212529;
        --text-light: #f8f9fa;
        --primary-gradient: linear-gradient(to right, #2c3e50, #4a6572);
        --hover-bg: rgba(255, 255, 255, 0.15);
        --active-bg: rgba(255, 255, 255, 0.2);
    }
    
    body {
        padding-top: 60px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .navbar {
        padding: 10px 0;
        background: var(--primary-gradient) !important;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    }
    
    .navbar-logo {
        width: 36px;
        height: 36px;
        object-fit: contain;
        border: none;
        border-radius: 4px;
        padding: 0;
        margin-right: 8px;
        /* Ensure GIF animation continues playing */
        animation-play-state: running !important;
        animation-iteration-count: infinite !important;
        -webkit-animation-play-state: running !important;
        -webkit-animation-iteration-count: infinite !important;
    }
    
    .navbar-brand {
        font-weight: 600;
        font-size: 1.2rem;
        color: white;
        padding-left: 10px;
    }
    
    .nav-link {
        font-weight: 500;
        color: rgba(255, 255, 255, 0.9) !important;
        transition: all 0.3s ease;
        padding: 6px 10px !important;
        margin: 0 3px;
    }
    
    .nav-link:hover, .nav-link:focus {
        color: white !important;
        background-color: var(--hover-bg);
    }
    
    .nav-link.active {
        background-color: var(--active-bg) !important;
        color: white !important;
    }
    
    .dropdown-menu {
        z-index: 1050;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        border: none;
        padding: 8px;
        margin-top: 10px;
    }
    
    .dropdown-menu-animated {
        animation: dropdownFade 0.2s ease-in-out;
    }
    
    @keyframes dropdownFade {
        from {opacity: 0; transform: translateY(-10px);}
        to {opacity: 1; transform: translateY(0);}
    }
    
    .dropdown-item {
        border-radius: 8px;
        padding: 8px 12px;
        transition: all 0.2s ease;
        font-size: 0.9rem;
        margin-bottom: 2px;
    }
    
    .dropdown-item:hover {
        background-color: var(--secondary-color);
        color: var(--primary-color);
        transform: translateX(3px);
    }
    
    .admin-nav {
        background-color: rgba(255, 215, 0, 0.15);
    }
    
    .admin-menu {
        min-width: 240px;
    }
    
    .admin-menu-header {
        font-weight: 600;
        color: var(--primary-color);
    }
    
    .profile-dropdown {
        min-width: 220px;
    }
    
    .profile-dropdown .dropdown-item {
        display: flex;
        align-items: center;
    }
    
    .profile-dropdown .dropdown-item i {
        margin-right: 8px;
        width: 16px;
        text-align: center;
    }
    
    .profile-image {
        border: 2px solid white;
        transition: all 0.3s ease;
        object-fit: cover;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .profile-image:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .notification-bell {
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .notification-bell:hover {
        background-color: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }
    
    /* Responsive adjustments */
    @media (max-width: 992px) {
        .navbar-collapse {
            max-height: 80vh;
            overflow-y: auto;
            background: linear-gradient(135deg, #2c3e50, #4a6572);
            border-radius: 12px;
            padding: 15px;
            margin-top: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .nav-link {
            padding: 10px 15px !important;
            margin: 3px 0;
            border-radius: 8px !important;
        }
        
        .dropdown-menu {
            background-color: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            margin-left: 15px;
            box-shadow: none;
        }
        
        .dropdown-item {
            color: rgba(255,255,255,0.9);
        }
        
        .dropdown-item:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
    }
</style>