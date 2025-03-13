<?php
ob_start();
include "./includes/header.php";

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: /subdisystem/user/login.php");
    exit();
}

// Fetch recent announcements
$query = "SELECT title, content, created_at FROM announcements ORDER BY created_at DESC LIMIT 3";
$result = $conn->query($query);
$announcements = $result->fetch_all(MYSQLI_ASSOC);

// Fetch upcoming bookings for the user
$user_id = $_SESSION['user_id'];
$bookings_query = "SELECT b.booking_id, b.booking_date, b.start_time, b.end_time, b.status, a.name as facility_name 
                  FROM bookings b 
                  JOIN amenities a ON b.facility_id = a.facility_id 
                  WHERE b.user_id = ? AND b.booking_date >= CURDATE() 
                  ORDER BY b.booking_date ASC, b.start_time ASC 
                  LIMIT 5";
$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings_result = $stmt->get_result();
$upcoming_bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);

// Fetch recent service requests
$service_query = "SELECT request_id, category, priority, status, created_at 
                 FROM service_requests 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT 3";
$stmt = $conn->prepare($service_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$service_result = $stmt->get_result();
$recent_requests = $service_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css">

 <style>
        /* Calendar View Improvements */
        .calendar-container {
        height: 500px;
        margin-top: 15px;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        background-color: #fff;
        padding: 5px;
        border: 1px solid #e5e7eb;
    }
    
    /* FullCalendar Customizations */
    .fc-theme-standard th {
        padding: 10px 0;
        background-color: #f8fafc;
        border-color: #e5e7eb;
    }
    
    .fc-theme-standard td {
        border-color: #e5e7eb;
    }
    
    .fc-theme-standard .fc-scrollgrid {
        border-color: #e5e7eb;
    }
    
    .fc-theme-standard .fc-button-primary {
        background-color: #3b82f6;
        border-color: #3b82f6;
        font-weight: 500;
        border-radius: 6px;
        padding: 6px 12px;
        transition: all 0.2s;
    }
    
    .fc-theme-standard .fc-button-primary:hover {
        background-color: #2563eb;
        border-color: #2563eb;
    }
    
    .fc-theme-standard .fc-button-primary:disabled {
        background-color: #93c5fd;
        border-color: #93c5fd;
    }
    
    .fc-theme-standard .fc-toolbar-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
    }
    
    .fc-theme-standard .fc-daygrid-day.fc-day-today {
        background-color: #eff6ff;
    }
    
    .fc-theme-standard .fc-event {
        border-radius: 6px;
        padding: 3px;
        font-size: 0.85rem;
        border: none;
    }
    
    .fc-theme-standard .fc-event-time {
        font-weight: 600;
    }
    
    .fc-theme-standard .fc-list-day-cushion {
        background-color: #f8fafc;
    }
    
    /* Service Requests Improvements */
    .service-request-item {
        padding: 18px;
        margin-bottom: 15px;
        border-radius: 10px;
        background-color: #f8f9fa;
        border-left: 4px solid #6b7280;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .service-request-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .service-request-item.urgent {
        border-left-color: #f97316;
    }
    
    .service-request-item.emergency {
        border-left-color: #ef4444;
    }
    
    .service-request-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    
    .service-request-title {
        font-weight: 600;
        font-size: 1.1rem;
        color: #333;
        margin: 0;
    }
    
    .service-request-meta {
        margin-top: 6px;
        display: flex;
        align-items: center;
        color: #666;
        font-size: 0.9rem;
    }
    
    .service-request-meta i {
        margin-right: 5px;
    }
    
    .service-request-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    /* Priority Badges Refined */
    .priority-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }
    
    .priority-badge i {
        margin-right: 4px;
        font-size: 0.75rem;
    }
    
    .priority-normal {
        background-color: #f3f4f6;
        color: #4b5563;
    }
    
    .priority-urgent {
        background-color: #ffedd5;
        color: #ea580c;
    }
    
    .priority-emergency {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    /* Status Badges Refined */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }
    
    .status-badge i {
        margin-right: 4px;
        font-size: 0.75rem;
    }
    
    .status-pending {
        background-color: #fef3c7;
        color: #d97706;
    }
    
    .status-confirmed {
        background-color: #dcfce7;
        color: #16a34a;
    }
    
    .status-canceled {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .status-in-progress {
        background-color: #dbeafe;
        color: #2563eb;
    }
    
    .status-completed {
        background-color: #f3f4f6;
        color: #4b5563;
    }
    
    /* Tab Content Transitions */
    .tab-content {
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .tab-content.active {
        display: block;
        opacity: 1;
    }
    
    /* Empty State Refinements */
    .no-bookings, .no-services {
        padding: 30px;
        text-align: center;
        color: #666;
        background-color: #f8f9fa;
        border-radius: 10px;
        margin: 15px 0;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.05);
    }
    
    .no-bookings p, .no-services p {
        margin-bottom: 20px;
        font-size: 1.05rem;
        color: #555;
    }
    
    .no-bookings i, .no-services i {
        font-size: 2.5rem;
        color: #cbd5e1;
        margin-bottom: 15px;
        display: block;
    }
    
    /* "View All" button refinement */
    .view-all-link {
        text-align: right;
        margin-top: 15px;
    }
    
    .view-all-link .btn {
        padding: 8px 16px;
        display: inline-flex;
        align-items: center;
    }
    
    .view-all-link .btn i {
        margin-left: 5px;
    }
    body {
        background: #f5f7fa;    
        font-family: 'Segoe UI', Arial, sans-serif;
        color: #444;
    }
    
    .dashboard-wrapper {
        max-width: 1200px;
        margin: 40px auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        padding: 24px;
        overflow: hidden;
    }
    
    /* Feature Cards */
    .feature-card {
        border: none;
        border-radius: 10px;
        padding: 24px;
        margin-bottom: 20px;
        height: 100%;
        background: #f8f9fa;
        cursor: pointer;
        transition: all 0.25s ease;
        text-decoration: none;
        color: #444;
        display: block;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
    }
    
    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        background: #fff;
    }
    
    .feature-card h5 {
        font-weight: 600;
        margin-bottom: 12px;
        color: #333;
        font-size: 1.15rem;
    }
    
    .feature-card p {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 0;
    }
    
    .feature-icon {
        font-size: 1.75rem;
        margin-right: 10px;
        margin-bottom: 15px;
        color: #3b82f6;
        display: block;
    }
    
    .property-icon {
        font-size: 1.75rem;
        margin-right: 10px;
        margin-bottom: 15px;
        color: #3b82f6;
        display: block;
    }
    
    /* Dashboard Summary Cards */
    .dashboard-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
        margin-top: 24px;
    }
    
    .summary-card {
        flex: 1;
        min-width: 200px;
        background-color: #fff;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .summary-card:hover {
        transform: translateY(-3px);
    }
    
    .summary-card .icon {
        margin-bottom: 10px;
    }
    
    .summary-card .count {
        font-size: 2.2rem;
        font-weight: bold;
        color: #3b82f6;
        margin: 10px 0;
    }
    
    .summary-card .label {
        color: #666;
        font-size: 0.95rem;
        font-weight: 500;
    }
    
    /* Section Containers */
    .section-container {
        background: #fff;
        border-radius: 10px;
        padding: 24px;
        margin-top: 24px;
        color: #444;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    .section-container h4 {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 20px;
        color: #333;
    }
    
    /* Dashboard Tabs */
    .dashboard-tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .dashboard-tab {
        padding: 12px 18px;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        margin-right: 12px;
        font-weight: 500;
        transition: all 0.2s;
        color: #666;
    }
    
    .dashboard-tab:hover {
        color: #3b82f6;
    }
    
    .dashboard-tab.active {
        border-bottom: 2px solid #3b82f6;
        color: #3b82f6;
        font-weight: 600;
    }
    
    /* Booking Items */
    .booking-item {
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 8px;
        background-color: #f8f9fa;
        border-left: 4px solid #3b82f6;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .booking-item:hover {
        transform: translateX(3px);
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    }
    
    .booking-item h5 {
        font-weight: 600;
        margin-bottom: 6px;
        color: #333;
    }
    
    .booking-item p {
        margin-bottom: 0;
        color: #666;
    }
    

    
    /* Announcements */
    .announcement-item {
        margin-bottom: 18px;
        padding-bottom: 18px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .announcement-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .announcement-item h5 {
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }
    
    /* Buttons */
    .btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background-color: #3b82f6;
        border-color: #3b82f6;
    }
    
    .btn-primary:hover {
        background-color: #2563eb;
        border-color: #2563eb;
    }
    
    .btn-outline-primary {
        color: #3b82f6;
        border-color: #3b82f6;
    }
    
    .btn-outline-primary:hover {
        background-color: #3b82f6;
        color: white;
    }
    

    
    /* Additional Text Colors */
    .text-primary {
        color: #3b82f6 !important;
    }
    
    .text-warning {
        color: #f59e0b !important;
    }
    
    .text-success {
        color: #10b981 !important;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .dashboard-wrapper {
            margin: 20px 15px;
            padding: 15px;
        }
        
        .feature-card {
            padding: 15px;
        }
        
        .dashboard-summary {
            gap: 10px;
        }
        
        .summary-card {
            min-width: calc(50% - 10px);
            padding: 15px;
        }
        
        .summary-card .count {
            font-size: 1.8rem;
        }
        
        .dashboard-tab {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            
            <div class="row">
                <div class="col-md-3">
                    <a href="/subdisystem/booking/create_booking.php" class="feature-card">
                        <h5><i class="fas fa-calendar-alt feature-icon"></i>Book Amenities</h5>
                        <p>Reserve community facilities easily.</p>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="/subdisystem/maintenance/create_request.php" class="feature-card">
                        <h5><i class="fas fa-tools feature-icon"></i>Maintenance Requests</h5>
                        <p>Submit and track repair requests.</p>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="/subdisystem/property/show_property.php" class="feature-card">
                        <h5><i class="fas fa-home property-icon"></i>Property Showcases</h5>
                        <p>Explore available properties in the subdivision.</p>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="/subdisystem/rentals/rent_item.php" class="feature-card">
                        <h5><i class="fas fa-box feature-icon"></i>Item Rentals</h5>
                        <p>Borrow equipment and items easily.</p>
                    </a>
                </div>
            </div>
            
            <!-- Activity Summary Cards -->
            <div class="dashboard-summary">
                <?php
                // Count upcoming bookings
                $count_query = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND booking_date >= CURDATE()";
                $stmt = $conn->prepare($count_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $upcoming_count = $stmt->get_result()->fetch_assoc()['count'];
                
                // Count pending service requests
                $count_query = "SELECT COUNT(*) as count FROM service_requests WHERE user_id = ? AND status != 'completed'";
                $stmt = $conn->prepare($count_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $service_count = $stmt->get_result()->fetch_assoc()['count'];
                
                // Count active rentals
                $count_query = "SELECT COUNT(*) as count FROM rentals WHERE user_id = ? AND status = 'approved' AND rental_end >= CURDATE()";
                $stmt = $conn->prepare($count_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $rental_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
                ?>
                
                <div class="summary-card">
                    <div class="icon"><i class="fas fa-calendar-check fa-2x text-primary"></i></div>
                    <div class="count"><?= $upcoming_count ?></div>
                    <div class="label">Upcoming Bookings</div>
                </div>
                
                <div class="summary-card">
                    <div class="icon"><i class="fas fa-tools fa-2x text-warning"></i></div>
                    <div class="count"><?= $service_count ?></div>
                    <div class="label">Active Service Requests</div>
                </div>
                
                <div class="summary-card">
                    <div class="icon"><i class="fas fa-box-open fa-2x text-success"></i></div>
                    <div class="count"><?= $rental_count ?></div>
                    <div class="label">Active Rentals</div>
                </div>
            </div>
            
            <!-- Bookings and Calendar Section -->
            <div class="section-container">
                <div class="dashboard-tabs">
                    <div class="dashboard-tab active" data-tab="upcoming">Upcoming Bookings</div>
                    <div class="dashboard-tab" data-tab="calendar">Calendar View</div>
                    <div class="dashboard-tab" data-tab="services">Service Requests</div>
                </div>
                
                <div id="upcoming-tab" class="tab-content active">
                    <h4>Your Upcoming Bookings</h4>
                    
                    <?php if (empty($upcoming_bookings)): ?>
                        <div class="no-bookings">
                            <p>You don't have any upcoming bookings.</p>
                            <a href="/subdisystem/booking/create_booking.php" class="btn btn-primary mt-2">Book Now</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_bookings as $booking): ?>
                            <div class="booking-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5><?= htmlspecialchars($booking['facility_name']); ?></h5>
                                        <p>
                                            <i class="far fa-calendar"></i> 
                                            <?= date('F d, Y', strtotime($booking['booking_date'])); ?> | 
                                            <i class="far fa-clock"></i> 
                                            <?= date('h:i A', strtotime($booking['start_time'])); ?> - 
                                            <?= date('h:i A', strtotime($booking['end_time'])); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <?php 
                                            $statusClass = '';
                                            switch($booking['status']) {
                                                case 'pending':
                                                    $statusClass = 'status-pending';
                                                    break;
                                                case 'confirmed':
                                                    $statusClass = 'status-confirmed';
                                                    break;
                                                case 'canceled':
                                                    $statusClass = 'status-canceled';
                                                    break;
                                            }
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= ucfirst(htmlspecialchars($booking['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-end mt-3">
                            <a href="/subdisystem/booking/view_bookings.php" class="btn btn-outline-primary">View All Bookings</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div id="calendar-tab" class="tab-content">
                    <h4>Schedule Calendar</h4>
                    <div id="booking-calendar" class="calendar-container"></div>
                </div>
                
                <div id="services-tab" class="tab-content">
                    <h4>Recent Service Requests</h4>
                    <?php if (empty($recent_requests)): ?>
                        <div class="no-bookings">
                            <p>You don't have any recent service requests.</p>
                            <a href="/subdisystem/maintenance/create_request.php" class="btn btn-primary mt-2">Submit Request</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_requests as $request): ?>
                            <?php 
                                $priorityClass = '';
                                switch($request['priority']) {
                                    case 'Normal':
                                        $priorityClass = 'priority-normal';
                                        $itemClass = '';
                                        break;
                                    case 'Urgent':
                                        $priorityClass = 'priority-urgent';
                                        $itemClass = 'urgent';
                                        break;
                                    case 'Emergency':
                                        $priorityClass = 'priority-emergency';
                                        $itemClass = 'emergency';
                                        break;
                                }
                                
                                $statusClass = '';
                                switch($request['status']) {
                                    case 'pending':
                                        $statusClass = 'status-pending';
                                        break;
                                    case 'in-progress':
                                        $statusClass = 'status-in-progress';
                                        break;
                                    case 'completed':
                                        $statusClass = 'status-completed';
                                        break;
                                }
                            ?>
                            <div class="service-request-item <?= $itemClass ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5><?= htmlspecialchars($request['category']); ?></h5>
                                        <p>
                                            <i class="far fa-calendar"></i> 
                                            <?= date('F d, Y', strtotime($request['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="priority-badge <?= $priorityClass ?>">
                                            <?= htmlspecialchars($request['priority']); ?>
                                        </span>
                                        <span class="status-badge <?= $statusClass ?> ml-2">
                                            <?= ucfirst(htmlspecialchars($request['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-end mt-3">
                            <a href="/subdisystem/maintenance/view_requests.php" class="btn btn-outline-primary">View All Requests</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Announcements Section -->
            <div class="section-container">
                <h4>Recent Announcements</h4>
                
                <?php 
                // If no announcements were fetched, display sample announcements from the image
                if (empty($announcements)): 
                ?>
                    <div class="announcement-item">
                        <h5>Community Meeting Scheduled</h5>
                        <p>Join us for the quarterly community meeting on March 25th, 2023.</p>
                    </div>
                    
                    <div class="announcement-item">
                        <h5>Pool Maintenance Update</h5>
                        <p>The swimming pool will undergo maintenance on April 5th, 2023.</p>
                    </div>
                    
                    <div class="announcement-item">
                        <h5>New Fitness Classes Available</h5>
                        <p>Sign up for our new fitness classes starting next week.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-item">
                            <h5><?= htmlspecialchars($announcement['title']); ?></h5>
                            <p><?= htmlspecialchars($announcement['content']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Initialize Bootstrap components
            console.log("Initializing Bootstrap Dropdowns...");
            var dropdownElements = document.querySelectorAll('.dropdown-toggle');
            dropdownElements.forEach(function (dropdown) {
                new bootstrap.Dropdown(dropdown);
            });
            console.log("Bootstrap Dropdowns Initialized!");
            
            // Tab switching functionality
            const tabs = document.querySelectorAll('.dashboard-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    // Remove active class from all tabs
                    document.querySelectorAll('.dashboard-tab').forEach(t => {
                        t.classList.remove('active');
                    });
                    
                    // Remove active class from all tab contents
                    document.querySelectorAll('.tab-content').forEach(t => {
                        t.classList.remove('active');
                    });
                    
                    // Add active class to clicked tab and its content
                    tab.classList.add('active');
                    document.getElementById(tabId + '-tab').classList.add('active');
                    
                    // Initialize calendar when switching to calendar tab
                    if (tabId === 'calendar') {
                        initializeCalendar();
                    }
                });
            });
            
            // Initialize calendar
            function initializeCalendar() {
                const calendarEl = document.getElementById('booking-calendar');
                
                // Check if calendar is already initialized
                if (calendarEl.classList.contains('fc')) return;
                
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,listWeek'
                    },
                    height: 'auto',
                    events: function(fetchInfo, successCallback, failureCallback) {
                        fetch('/subdisystem/booking/get_bookings.php')
                            .then(response => response.json())
                            .then(data => {
                                const events = data.map(booking => ({
                                    title: booking.facility_name,
                                    start: booking.booking_date + 'T' + booking.start_time,
                                    end: booking.booking_date + 'T' + booking.end_time,
                                    backgroundColor: booking.status === 'confirmed' ? '#28a745' : 
                                                booking.status === 'pending' ? '#ffc107' : '#dc3545',
                                    borderColor: booking.status === 'confirmed' ? '#28a745' : 
                                                booking.status === 'pending' ? '#ffc107' : '#dc3545',
                                    textColor: booking.status === 'pending' ? '#212529' : '#ffffff',
                                    extendedProps: {
                                        status: booking.status,
                                        bookingId: booking.booking_id
                                    }
                                }));
                                successCallback(events);
                            })
                            .catch(error => {
                                console.error('Error fetching events:', error);
                                failureCallback(error);
                            });
                    }
                });
                
                calendar.render();
            }

            // Bootstrap click handler
            document.addEventListener("click", function (event) {
                if (event.target.matches(".dropdown-toggle")) {
                    let dropdown = new bootstrap.Dropdown(event.target);
                    dropdown.show();
                }
            });

            console.log("Bootstrap version:", bootstrap?.Dropdown ? "Loaded" : "Not Loaded");
        });
    </script>
</body>
</html>
<?php include './includes/footer.php';?>