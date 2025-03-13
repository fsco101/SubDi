<?php

include "./includes/header.php";

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Access Denied. Please log in.");
}

// Fetch user role from users table
$user_id = $_SESSION['user_id'];
$user_query = "SELECT role FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

// Restrict access to admin only
if (!isset($user['role']) || $user['role'] !== 'admin') {
    die("Access Denied. You do not have permission to access this page.");
}

// Fetch all bookings
$bookings_query = "SELECT 
                      b.booking_id, 
                      b.booking_date, 
                      b.start_time, 
                      b.end_time, 
                      b.status, 
                      a.name AS facility_name, 
                      CONCAT(u.f_name, ' ', u.l_name) AS user_name 
                  FROM bookings b
                  JOIN amenities a ON b.facility_id = a.facility_id
                  JOIN users u ON b.user_id = u.user_id
                  ORDER BY b.booking_date DESC";
$bookings_stmt = $conn->prepare($bookings_query);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
$bookings_stmt->close();

// Fetch all service requests
$service_query = "SELECT 
                      sr.request_id, 
                      sr.category, 
                      sr.priority, 
                      sr.status, 
                      sr.created_at, 
                      CONCAT(u.f_name, ' ', u.l_name) AS user_name 
                  FROM service_requests sr
                  JOIN users u ON sr.user_id = u.user_id
                  ORDER BY sr.created_at DESC";
$service_stmt = $conn->prepare($service_query);
$service_stmt->execute();
$service_result = $service_stmt->get_result();
$service_requests = $service_result->fetch_all(MYSQLI_ASSOC);
$service_stmt->close();

// Fetch all rental transactions
$rentals_query = "SELECT 
                      r.rental_id, 
                      r.rental_start, 
                      r.rental_end, 
                      r.status, 
                      ri.name AS item_name, 
                      CONCAT(u.f_name, ' ', u.l_name) AS user_name 
                  FROM rentals r
                  JOIN rental_items ri ON r.item_id = ri.item_id
                  JOIN users u ON r.user_id = u.user_id
                  ORDER BY r.rental_start DESC";
$rentals_stmt = $conn->prepare($rentals_query);
$rentals_stmt->execute();
$rentals_result = $rentals_stmt->get_result();
$rentals = $rentals_result->fetch_all(MYSQLI_ASSOC);
$rentals_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css">
    <style>
        :root {
            --primary-color: #4a6cf7;
            --secondary-color: #f5f7fa;
            --success-color: #0abb87;
            --info-color: #17a2b8;
            --warning-color: #ffb822;
            --danger-color: #ee5455;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --body-bg: #f6f8fb;
            --card-bg: #ffffff;
            --text-color: #212529;
            --border-color: #e4e7ed;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--body-bg);
            color: var(--text-color);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-wrapper {
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .dashboard-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dashboard-header h2 {
            margin: 0;
            font-weight: 600;
        }
        
        .dashboard-tabs {
            display: flex;
            background-color: var(--secondary-color);
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
        }
        
        .dashboard-tab {
            padding: 15px 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
        }
        
        .dashboard-tab:hover {
            background-color: rgba(74, 108, 247, 0.1);
        }
        
        .dashboard-tab.active {
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            padding: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-content h4 {
            margin-bottom: 20px;
            font-weight: 600;
            color: var(--dark-color);
            border-left: 4px solid var(--primary-color);
            padding-left: 10px;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .item-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 15px;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s;
        }
        
        .item-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .booking-item {
            border-left-color: var(--primary-color);
        }
        
        .service-request-item {
            border-left-color: var(--warning-color);
        }
        
        .rental-item {
            border-left-color: var(--info-color);
        }
        
        .item-card h5 {
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .item-card p {
            margin-bottom: 5px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .item-card p:last-child {
            margin-bottom: 0;
        }
        
        .item-card .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
        }
        
        .status-pending {
            background-color: var(--warning-color);
        }
        
        .status-confirmed {
            background-color: var(--success-color);
        }
        
        .status-cancelled {
            background-color: var(--danger-color);
        }
        
        .status-completed {
            background-color: var(--info-color);
        }
        
        .calendar-container {
            height: 650px;
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        @media (max-width: 768px) {
            .dashboard-tabs {
                flex-wrap: wrap;
            }
            
            .dashboard-tab {
                padding: 10px 15px;
            }
            
            .grid-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
                <div class="header-actions">
                    <span><?php echo date('F d, Y'); ?></span>
                </div>
            </div>
            
            <div class="dashboard-tabs">
                <div class="dashboard-tab active" data-tab="bookings">
                    <i class="fas fa-calendar-check"></i> Bookings
                </div>
                <div class="dashboard-tab" data-tab="services">
                    <i class="fas fa-tools"></i> Service Requests
                </div>
                <div class="dashboard-tab" data-tab="rentals">
                    <i class="fas fa-box"></i> Rentals
                </div>
                <div class="dashboard-tab" data-tab="calendar">
                    <i class="fas fa-calendar-alt"></i> Calendar
                </div>
            </div>

            <div id="bookings-tab" class="tab-content active">
                <h4>All Bookings</h4>
                <div class="grid-container">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="item-card booking-item">
                            <h5><i class="fas fa-building"></i> <?= htmlspecialchars($booking['facility_name']); ?></h5>
                            <p><i class="fas fa-user"></i> <?= htmlspecialchars($booking['user_name']); ?></p>
                            <p><i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($booking['booking_date'])); ?></p>
                            <p><i class="fas fa-clock"></i> <?= htmlspecialchars($booking['start_time']); ?> - <?= htmlspecialchars($booking['end_time']); ?></p>
                            <p>
                                <span class="status status-<?= strtolower(htmlspecialchars($booking['status'])); ?>">
                                    <?= ucfirst(htmlspecialchars($booking['status'])); ?>
                                </span>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="services-tab" class="tab-content">
                <h4>All Service Requests</h4>
                <div class="grid-container">
                    <?php foreach ($service_requests as $request): ?>
                        <div class="item-card service-request-item">
                            <h5><i class="fas fa-tag"></i> <?= htmlspecialchars($request['category']); ?></h5>
                            <p><i class="fas fa-user"></i> <?= htmlspecialchars($request['user_name']); ?></p>
                            <p><i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($request['created_at'])); ?></p>
                            <p><i class="fas fa-signal"></i> Priority: <?= ucfirst(htmlspecialchars($request['priority'])); ?></p>
                            <p>
                                <span class="status status-<?= strtolower(htmlspecialchars($request['status'])); ?>">
                                    <?= ucfirst(htmlspecialchars($request['status'])); ?>
                                </span>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="rentals-tab" class="tab-content">
                <h4>All Rentals</h4>
                <div class="grid-container">
                    <?php foreach ($rentals as $rental): ?>
                        <div class="item-card rental-item">
                            <h5><i class="fas fa-box"></i> <?= htmlspecialchars($rental['item_name']); ?></h5>
                            <p><i class="fas fa-user"></i> <?= htmlspecialchars($rental['user_name']); ?></p>
                            <p><i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($rental['rental_start'])); ?> to <?= date('F d, Y', strtotime($rental['rental_end'])); ?></p>
                            <p>
                                <span class="status status-<?= strtolower(htmlspecialchars($rental['status'])); ?>">
                                    <?= ucfirst(htmlspecialchars($rental['status'])); ?>
                                </span>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="calendar-tab" class="tab-content">
                <h4>Schedule Calendar</h4>
                <div id="booking-calendar" class="calendar-container"></div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Tab switching functionality
            document.querySelectorAll('.dashboard-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.dashboard-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    document.getElementById(tab.getAttribute('data-tab') + '-tab').classList.add('active');
                    
                    // Initialize calendar when calendar tab is selected
                    if (tab.getAttribute('data-tab') === 'calendar') {
                        initializeCalendar();
                    }
                });
            });
            
            // Initialize FullCalendar
            function initializeCalendar() {
                const calendarEl = document.getElementById('booking-calendar');
                if (calendarEl.classList.contains('fc')) return; // Prevent re-initialization
                
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: '/subdisystem/booking/get_bookings.php',
                    height: '100%',
                    themeSystem: 'bootstrap',
                    eventClick: function(info) {
                        // Handle event click
                        alert('Booking: ' + info.event.title);
                    }
                });
                
                calendar.render();
            }
        });

                    // Bootstrap click handler
                    document.addEventListener("click", function (event) {
                if (event.target.matches(".dropdown-toggle")) {
                    let dropdown = new bootstrap.Dropdown(event.target);
                    dropdown.show();
                }
            });

            console.log("Bootstrap version:", bootstrap?.Dropdown ? "Loaded" : "Not Loaded");
        
    </script>
</body>
</html>