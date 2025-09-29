<?php


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
    <link href="https://unpkg.com/@fullcalendar/daygrid/main.css" rel="stylesheet">
    <link href="https://unpkg.com/@fullcalendar/timegrid/main.css" rel="stylesheet">
    <link href="https://unpkg.com/@fullcalendar/list/main.css" rel="stylesheet">
    <script src="https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/animations/shift-away.css">
    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>
    <div class="container">
        <div class="image-section"></div>
        <div class="dashboard-wrapper">
            <!-- Add Charts Section -->
            <div class="charts-section">
                <h2 class="section-title">Community Activity Dashboard</h2>
                <div class="charts-container">
                    <div class="chart-card">
                        <h3>Most Popular Facilities</h3>
                        <div class="chart-wrapper">
                            <canvas id="bookingsChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <h3>Service Request Categories</h3>
                        <div class="chart-wrapper">
                            <canvas id="serviceRequestsChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <h3>Popular Item Rentals</h3>
                        <div class="chart-wrapper">
                            <canvas id="rentalsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
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
                        <h5><i class="fas fa-home feature-icon"></i>Property Showcases</h5>
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
                            <!-- Announcements Section -->
            <div class="announcement-container">
                <h2 class="announcement-section-title">Important Announcements</h2>
                
                <?php if (empty($announcements)): ?>
                    <div class="announcement-card">
                        <div class="announcement-body">
                            <p>No announcements at this time.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-card">
                            <div class="announcement-header">
                                <span>Announcement</span>
                                <span class="announcement-date"><?= date('F d, Y', strtotime($announcement['created_at'])); ?></span>
                            </div>
                            <div class="announcement-body">
                                <h3 class="announcement-title"><?= htmlspecialchars($announcement['title']); ?></h3>
                                <div class="announcement-content">
                                    <p><?= nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                </div>
                            </div>
                            <div class="announcement-footer">
                                <div class="announcement-badges">
                                    <span class="announcement-badge important">Important</span>
                                </div>
                                <div class="announcement-actions">
                                    <a href="/subdisystem/announcements/view_announcement.php" class="btn btn-sm btn-outline-primary">Read More</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
                <div class="dashboard-tabs">
                    <div class="dashboard-tab active" data-tab="upcoming">Upcoming Bookings</div>
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
                            <div class="booking-item" data-facility="<?= htmlspecialchars($booking['facility_name']); ?>">
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
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
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
    
    // Bootstrap click handler
    document.addEventListener("click", function (event) {
        if (event.target.matches(".dropdown-toggle")) {
            let dropdown = new bootstrap.Dropdown(event.target);
            dropdown.show();
        }
    });

    console.log("Bootstrap version:", bootstrap?.Dropdown ? "Loaded" : "Not Loaded");
    
    // Initialize Charts
    initCharts();
    
    // Calendar initialization
    let calendar = null;
    
    // Initialize calendar - this will be called when switching to calendar tab
    function initializeCalendar() {
        const calendarEl = document.getElementById('booking-calendar');
        
        // Check if calendar is already initialized
        if (calendarEl.classList.contains('fc')) return;
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek', // Schedule view as default
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'listWeek' // Removed dayGridMonth, timeGridWeek, and timeGridDay
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            height: 'auto',
            nowIndicator: true,
            navLinks: true,
            businessHours: {
                daysOfWeek: [1, 2, 3, 4, 5],
                startTime: '08:00',
                endTime: '18:00'
            },
            views: {
                timeGridWeek: {
                    dayHeaderFormat: { weekday: 'long', month: 'numeric', day: 'numeric' }
                }
            },
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                meridiem: true
            },
            eventDidMount: function(info) {
                // Enhanced event styling
                info.el.style.borderLeft = '4px solid #16a34a';
                
                // Add tooltips with Tippy.js if available
                if (window.tippy) {
                    tippy(info.el, {
                        content: `<div class="event-tooltip">
                            <h4>${info.event.title}</h4>
                            <p><i class="fas fa-calendar-check"></i> ${info.event.start.toLocaleDateString()}</p>
                            <p><i class="fas fa-clock"></i> ${info.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} - 
                               ${info.event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                            <p><i class="fas fa-check-circle"></i> Approved Booking</p>
                        </div>`,
                        allowHTML: true,
                        placement: 'top',
                        animation: 'shift-away',
                        arrow: true,
                        theme: 'light'
                    });
                }
            },
            events: function(info, successCallback, failureCallback) {
                // Show loading indicator
                const loadingIndicator = document.getElementById('calendar-loading');
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'flex';
                }
                
                // Fetch the user's bookings
                fetch('/subdisystem/api/get_user_bookings.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Filter for confirmed/approved bookings only
                        const approvedBookings = data.filter(booking => 
                            booking.status === 'confirmed' || booking.status === 'approved');
                        
                        // Also populate the approved bookings list
                        displayApprovedBookingsList(approvedBookings);
                        
                        // Format the events
                        const events = approvedBookings.map(booking => ({
                            id: booking.booking_id,
                            title: booking.facility_name,
                            start: booking.booking_date + 'T' + booking.start_time,
                            end: booking.booking_date + 'T' + booking.end_time,
                            backgroundColor: '#4ade80',
                            borderColor: '#16a34a',
                            textColor: 'white',
                            extendedProps: {
                                status: booking.status,
                                facility: booking.facility_name,
                                bookingId: booking.booking_id
                            }
                        }));
                        
                        // Pass the events to the calendar
                        successCallback(events);
                    })
                    .catch(error => {
                        console.error('Error fetching bookings:', error);
                        failureCallback(error);
                        
                        // Display error message in the approved bookings list
                        const bookingsList = document.getElementById('approved-bookings-list');
                        if (bookingsList) {
                            bookingsList.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    Error loading your bookings. Please try again later.
                                </div>
                            `;
                        }
                    })
                    .finally(() => {
                        // Hide loading indicator
                        if (loadingIndicator) {
                            loadingIndicator.style.display = 'none';
                        }
                    });
            },
            eventClick: function(info) {
                showEventModal(info.event);
            }
        });
        
        calendar.render();
        
        // Add a heading to clarify these are approved bookings
        const calendarTab = document.getElementById('calendar-tab');
        if (calendarTab) {
            const calendarHeader = calendarTab.querySelector('h4');
            if (calendarHeader) {
                calendarHeader.textContent = 'My Approved Bookings Schedule';
            }
        }
    }
    
    // Function to display approved bookings in list
    function displayApprovedBookingsList(bookings) {
        const container = document.getElementById('approved-bookings-list');
        if (!container) return;
        
        if (!bookings || bookings.length === 0) {
            container.innerHTML = `
                <div class="no-bookings-calendar">
                    <i class="fas fa-calendar-times"></i>
                    <p>You don't have any approved bookings yet.</p>
                    <a href="/subdisystem/booking/create_booking.php">Book a Facility Now</a>
                </div>
            `;
            return;
        }
        
        // Sort bookings by date
        bookings.sort((a, b) => new Date(a.booking_date + 'T' + a.start_time) - new Date(b.booking_date + 'T' + b.start_time));
        
        let html = '';
        bookings.forEach(booking => {
            const bookingDate = new Date(booking.booking_date + 'T' + booking.start_time);
            const formattedDate = bookingDate.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const startTime = formatTime(booking.start_time);
            const endTime = formatTime(booking.end_time);
            
            html += `
                <div class="approved-booking-item" data-booking-id="${booking.booking_id}">
                    <span class="booking-date">${formattedDate}</span>
                    <h5>${booking.facility_name}</h5>
                    <p><i class="fas fa-clock me-2"></i>${startTime} - ${endTime}</p>
                    <div class="approved-booking-actions">
                        <button onclick="viewBookingDetails(${booking.booking_id})">
                            <i class="fas fa-eye me-1"></i> View Details
                        </button>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    // Helper function to format time
    function formatTime(timeString) {
        const [hours, minutes] = timeString.split(':');
        let hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12;
        hour = hour ? hour : 12; // Convert 0 to 12
        return `${hour}:${minutes} ${ampm}`;
    }
    
    // Add to global scope for the button onclick
    window.viewBookingDetails = function(bookingId) {
        window.location.href = `/subdisystem/booking/view_booking.php?id=${bookingId}`;
    };
    
    // Event modal creation and display functions
    function showEventModal(event) {
        const modal = document.getElementById('event-details-modal');
        if (!modal) {
            createEventModal();
            showEventModal(event);
            return;
        }
        
        // Fill modal with event details
        document.getElementById('modal-title').textContent = event.title;
        document.getElementById('modal-date').textContent = event.start.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        document.getElementById('modal-time').textContent = `${event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} - ${event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
        document.getElementById('modal-status').textContent = 'Approved';
        document.getElementById('modal-status').className = 'status-badge status-confirmed';
        
        // Set the booking ID for the view details button
        if (event.extendedProps && event.extendedProps.bookingId) {
            document.getElementById('modal-booking-id').value = event.extendedProps.bookingId;
        }
        
        // Show modal with animation
        modal.style.display = 'flex';
        setTimeout(() => {
            document.querySelector('.event-modal-content').classList.add('show');
        }, 10);
    }
    
    function createEventModal() {
        const modalHtml = `
        <div id="event-details-modal" class="event-modal">
            <div class="event-modal-content">
                <span class="event-modal-close">&times;</span>
                <h3 id="modal-title"></h3>
                <div class="event-details">
                    <p><i class="fas fa-calendar"></i> <span id="modal-date"></span></p>
                    <p><i class="fas fa-clock"></i> <span id="modal-time"></span></p>
                    <p><i class="fas fa-info-circle"></i> Status: <span id="modal-status"></span></p>
                </div>
                <div class="modal-actions">
                    <button class="btn-view-details">View Details</button>
                </div>
            </div>
        </div>`;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Add event listener for close button
        document.querySelector('.event-modal-close').addEventListener('click', function() {
            document.getElementById('event-details-modal').style.display = 'none';
        });
        
        // Add event listener for view details button
        document.querySelector('.btn-view-details').addEventListener('click', function() {
            // Add your logic to navigate to booking details page
            const bookingId = document.getElementById('modal-booking-id').value;
            window.location.href = `/subdisystem/booking/view_booking.php?id=${bookingId}`;
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('event-details-modal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Add hidden field for booking ID
        const modalContent = document.querySelector('.event-modal-content');
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.id = 'modal-booking-id';
        modalContent.appendChild(hiddenField);
    }
    
    // Charts functionality
    function initCharts() {
        // Define chart colors
        const chartColors = {
            blue: 'rgba(59, 130, 246, 0.7)',
            green: 'rgba(16, 185, 129, 0.7)',
            purple: 'rgba(139, 92, 246, 0.7)',
            orange: 'rgba(249, 115, 22, 0.7)',
            red: 'rgba(239, 68, 68, 0.7)',
            borderBlue: 'rgba(37, 99, 235, 1)',
            borderGreen: 'rgba(5, 150, 105, 1)',
            borderPurple: 'rgba(124, 58, 237, 1)',
            borderOrange: 'rgba(234, 88, 12, 1)',
            borderRed: 'rgba(220, 38, 38, 1)'
        };
        
        // Fetch bookings data for ALL users
        fetchChartData('bookings').then(data => {
            if (data && data.length > 0) {
                renderDoughnutChart('bookingsChart', data, 'facility_name', 'count', 'Booking Count by Facility', chartColors.blue, chartColors.borderBlue);
            } else {
                displayNoDataMessage('bookingsChart', 'No booking data available');
            }
        });
        
        // Fetch service requests data for ALL users
        fetchChartData('service_requests').then(data => {
            if (data && data.length > 0) {
                renderBarChart('serviceRequestsChart', data, 'category', 'count', 'Requests by Category', chartColors.purple, chartColors.borderPurple);
            } else {
                displayNoDataMessage('serviceRequestsChart', 'No service request data available');
            }
        });
        
        // Fetch rentals data for ALL users
        fetchChartData('rentals').then(data => {
            if (data && data.length > 0) {
                renderDoughnutChart('rentalsChart', data, 'item_name', 'count', 'Rental Count by Item', chartColors.green, chartColors.borderGreen);
            } else {
                displayNoDataMessage('rentalsChart', 'No rental data available');
            }
        });
    }
    
    // Function to fetch chart data
    async function fetchChartData(type) {
        try {
            const response = await fetch(`/subdisystem/api/get_chart_data.php?type=${type}`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return await response.json();
        } catch (error) {
            console.error('Error fetching chart data:', error);
            return null;
        }
    }
    
    // Function to render doughnut chart
    function renderDoughnutChart(canvasId, data, labelKey, valueKey, title, bgColor, borderColor) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        // Extract labels and values
        const labels = data.map(item => item[labelKey]);
        const values = data.map(item => item[valueKey]);
        
        // Use consistent color scheme based on category types
        const categoryColors = getCategoryColors(labels, canvasId);
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: categoryColors.backgroundColors,
                    borderColor: categoryColors.borderColors,
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: {
                                family: "'Segoe UI', Arial, sans-serif",
                                size: 12
                            },
                            color: '#475569'
                        }
                    },
                    title: {
                        display: false,
                        text: title,
                        font: {
                            family: "'Segoe UI', Arial, sans-serif",
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#475569',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%',
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    }
    
    // Function to render bar chart
    function renderBarChart(canvasId, data, labelKey, valueKey, title, bgColor, borderColor) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        // Extract labels and values
        const labels = data.map(item => item[labelKey]);
        const values = data.map(item => item[valueKey]);
        
        // Use consistent color scheme based on category types
        const categoryColors = getCategoryColors(labels, canvasId);
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Count',
                    data: values,
                    backgroundColor: categoryColors.backgroundColors,
                    borderColor: categoryColors.borderColors,
                    borderWidth: 2,
                    borderRadius: 6,
                    maxBarThickness: 60
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: false,
                        text: title
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#475569',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: '#64748b',
                            font: {
                                family: "'Segoe UI', Arial, sans-serif"
                            }
                        },
                        grid: {
                            color: '#e2e8f0',
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            color: '#64748b',
                            font: {
                                family: "'Segoe UI', Arial, sans-serif"
                            }
                        },
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    }
                },
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                }
            }
        });
    }
    
    // Function to get consistent colors based on category
    function getCategoryColors(labels, chartType) {
        // Color definitions for different categories
        const colorMap = {
            // Booking/Amenity colors (facilities)
            'Swimming Pool': { bg: 'rgba(59, 130, 246, 0.7)', border: 'rgba(37, 99, 235, 1)' },
            'Tennis Court': { bg: 'rgba(16, 185, 129, 0.7)', border: 'rgba(5, 150, 105, 1)' },
            'Basketball Court': { bg: 'rgba(249, 115, 22, 0.7)', border: 'rgba(234, 88, 12, 1)' },
            'Function Hall': { bg: 'rgba(139, 92, 246, 0.7)', border: 'rgba(124, 58, 237, 1)' },
            'Gym': { bg: 'rgba(236, 72, 153, 0.7)', border: 'rgba(219, 39, 119, 1)' },
            
            // Service Request colors
            'Plumbing': { bg: 'rgba(59, 130, 246, 0.7)', border: 'rgba(37, 99, 235, 1)' },
            'Electrical': { bg: 'rgba(239, 68, 68, 0.7)', border: 'rgba(220, 38, 38, 1)' },
            'Carpentry': { bg: 'rgba(245, 158, 11, 0.7)', border: 'rgba(217, 119, 6, 1)' },
            'Landscaping': { bg: 'rgba(16, 185, 129, 0.7)', border: 'rgba(5, 150, 105, 1)' },
            'Security': { bg: 'rgba(139, 92, 246, 0.7)', border: 'rgba(124, 58, 237, 1)' },
            'Cleaning': { bg: 'rgba(14, 165, 233, 0.7)', border: 'rgba(2, 132, 199, 1)' },
            
            // Rental items colors
            'Chairs': { bg: 'rgba(249, 115, 22, 0.7)', border: 'rgba(234, 88, 12, 1)' },
            'Tables': { bg: 'rgba(139, 92, 246, 0.7)', border: 'rgba(124, 58, 237, 1)' },
            'Speakers': { bg: 'rgba(239, 68, 68, 0.7)', border: 'rgba(220, 38, 38, 1)' },
            'Projector': { bg: 'rgba(16, 185, 129, 0.7)', border: 'rgba(5, 150, 105, 1)' },
            'Tents': { bg: 'rgba(59, 130, 246, 0.7)', border: 'rgba(37, 99, 235, 1)' }
        };
        
        // Default colors for items not in the map
        const defaultColors = [
            { bg: 'rgba(59, 130, 246, 0.7)', border: 'rgba(37, 99, 235, 1)' },
            { bg: 'rgba(16, 185, 129, 0.7)', border: 'rgba(5, 150, 105, 1)' },
            { bg: 'rgba(249, 115, 22, 0.7)', border: 'rgba(234, 88, 12, 1)' },
            { bg: 'rgba(139, 92, 246, 0.7)', border: 'rgba(124, 58, 237, 1)' },
            { bg: 'rgba(239, 68, 68, 0.7)', border: 'rgba(220, 38, 38, 1)' },
            { bg: 'rgba(236, 72, 153, 0.7)', border: 'rgba(219, 39, 119, 1)' },
            { bg: 'rgba(245, 158, 11, 0.7)', border: 'rgba(217, 119, 6, 1)' }
        ];
        
        // Prepare background and border color arrays
        const backgroundColors = [];
        const borderColors = [];
        
        labels.forEach((label, index) => {
            if (colorMap[label]) {
                backgroundColors.push(colorMap[label].bg);
                borderColors.push(colorMap[label].border);
            } else {
                // Use default colors in rotation if no specific color is defined
                const defaultIndex = index % defaultColors.length;
                backgroundColors.push(defaultColors[defaultIndex].bg);
                borderColors.push(defaultColors[defaultIndex].border);
            }
        });
        
        return { backgroundColors, borderColors };
    }
    
    // Function to display no data message
    function displayNoDataMessage(canvasId, message) {
        // ...existing code...
    }
    
    // Add color legend section after chart initialization
    function addColorLegend() {
        // Create legend container if it doesn't exist
        let legendContainer = document.getElementById('dashboard-color-legend');
        if (!legendContainer) {
            legendContainer = document.createElement('div');
            legendContainer.id = 'dashboard-color-legend';
            legendContainer.className = 'color-legend-container';
            

            
            // Create each section
            sections.forEach(section => {
                const sectionElement = document.createElement('div');
                sectionElement.className = 'legend-section';
                
                const sectionTitle = document.createElement('h4');
                sectionTitle.textContent = section.title;
                sectionElement.appendChild(sectionTitle);
                
                const itemsContainer = document.createElement('div');
                itemsContainer.className = 'legend-items';
                
                section.items.forEach(item => {
                    const itemElement = document.createElement('div');
                    itemElement.className = 'legend-item';
                    
                    const colorBox = document.createElement('span');
                    colorBox.className = 'color-box';
                    colorBox.style.backgroundColor = item.color;
                    
                    const labelText = document.createElement('span');
                    labelText.textContent = item.label;
                    
                    itemElement.appendChild(colorBox);
                    itemElement.appendChild(labelText);
                    itemsContainer.appendChild(itemElement);
                });
                
                sectionElement.appendChild(itemsContainer);
                legendContainer.appendChild(sectionElement);
            });
            
            // Add toggle button for mobile
            const toggleButton = document.createElement('button');
            toggleButton.className = 'legend-toggle';
            toggleButton.innerHTML = 'Color Guide <i class="fas fa-palette"></i>';
            toggleButton.onclick = function() {
                legendContainer.classList.toggle('expanded');
            };
            
            // Add to document
            const chartsSection = document.querySelector('.charts-section');
            if (chartsSection) {
                chartsSection.appendChild(toggleButton);
                chartsSection.appendChild(legendContainer);
            } else {
                document.querySelector('.charts-container').parentNode.appendChild(toggleButton);
                document.querySelector('.charts-container').parentNode.appendChild(legendContainer);
            }
            
            // Add event listener to close legend when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 && 
                    !legendContainer.contains(event.target) && 
                    !toggleButton.contains(event.target) &&
                    legendContainer.classList.contains('expanded')) {
                    legendContainer.classList.remove('expanded');
                }
            });
        }
    }
    
    // Initialize Charts
    initCharts();
    
    // Add the color legend after charts are initialized
    setTimeout(addColorLegend, 500);
    
    // Calendar initialization
    // ...existing code...
});
</script>

<style>
    /* Chart Section Styles */
    .charts-section {
        margin-bottom: 40px;
        padding-bottom: 20px;
    }
    
    .section-title {
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 25px;
        color: #1e293b;
        letter-spacing: -0.02em;
        background-clip: text;
        -webkit-background-clip: text;
        background-image: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        position: relative;
        padding-bottom: 10px;
    }
    
    .section-title:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 4px;
        background: linear-gradient(to right, #3b82f6, #2563eb);
        border-radius: 2px;
    }
    
    .charts-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }
    
    .chart-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.01), 0 2px 4px rgba(0, 0, 0, 0.02);
        padding: 20px;
        transition: all 0.3s ease;
        border: 1px solid rgba(241, 245, 249, 0.8);
        position: relative;
        overflow: hidden;
    }
    
    .chart-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px rgba(0, 0, 0, 0.06);
    }
    
    .chart-card h3 {
        font-size: 1.1rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .chart-wrapper {
        height: 250px;
        position: relative;
        margin-top: 10px;
    }
    
    /* Responsive styles for charts */
    @media (max-width: 992px) {
        .charts-container {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        .charts-container {
            grid-template-columns: 1fr;
        }
        
        .chart-wrapper {
            height: 220px;
        }
    }

    /* General Styles - Enhanced */
    body {
        background: linear-gradient(135deg, #f6f9fc 0%, #f1f5f9 100%);    
        font-family: 'Segoe UI', Arial, sans-serif;
        color: #334155;
    }
    
    .container {
        max-width: 1400px;
        margin: 20px auto;
        padding: 0 15px;
        width: 95%;
    }
    
    .dashboard-wrapper {
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05), 0 5px 10px rgba(0, 0, 0, 0.02);
        padding: 30px;
        overflow: hidden;
        transition: all 0.3s ease;
        margin-bottom: 40px;
    }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    /* Feature Cards - Glamorous version */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -15px;
    }
    
    .col-md-3 {
        flex: 0 0 25%;
        max-width: 25%;
        padding: 0 15px;
        margin-bottom: 30px;
    }
    
    .feature-card {
        text-decoration: none;
        color: #475569;
        display: block;
        transition: all 0.3s ease;
        padding: 28px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02), 0 1px 3px rgba(0, 0, 0, 0.03);
        background: #fff;
        border: 1px solid rgba(226, 232, 240, 0.8);
        position: relative;
        overflow: hidden;
        z-index: 1;
    }
    
    .feature-card:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(37, 99, 235, 0.1) 100%);
        z-index: -1;
        transform: translateY(100%);
        transition: transform 0.35s ease;
    }
    
    .feature-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
    }
    
    .feature-card:hover:before {
        transform: translateY(0);
    }
    
    .feature-card h5 {
        font-weight: 600;
        margin-bottom: 15px;
        color: #1e293b;
        font-size: 1.15rem;
        letter-spacing: -0.01em;
    }
    
    .feature-card p {
        color: #64748b;
        font-size: 0.9rem;
        margin-bottom: 0;
        line-height: 1.5;
    }
    
    .feature-icon {
        font-size: 2rem;
        margin-bottom: 20px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: block;
    }
    
    /* Dashboard Summary */
    .dashboard-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin: 30px 0;
    }
    
    .summary-card {
        flex: 1;
        min-width: 220px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 12px;
        padding: 25px;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.01), 0 2px 4px rgba(0, 0, 0, 0.02);
        transition: all 0.3s ease;
        border: 1px solid rgba(241, 245, 249, 0.8);
        position: relative;
        overflow: hidden;
    }
    
    .summary-card:after {
        content: '';
        position: absolute;
        width: 100%;
        height: 3px;
        background: linear-gradient(to right, #3b82f6, #2563eb);
        bottom: 0;
        left: 0;
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s ease;
    }
    
    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }
    
    .summary-card:hover:after {
        transform: scaleX(1);
    }
    
    .summary-card .count {
        font-size: 2.8rem;
        font-weight: 700;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 10px 0;
        line-height: 1;
        animation: pulse 2s infinite alternate;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        100% { transform: scale(1.05); }
    }
    
    .summary-card .icon {
        position: relative;
        display: inline-block;
        animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-5px); }
        100% { transform: translateY(0px); }
    }
    
    .summary-card .label {
        color: #64748b;
        font-size: 1rem;
        font-weight: 500;
        letter-spacing: -0.01em;
    }
    
    /* Sections */
    .section-container {
        background: #fff;
        border-radius: 16px;
        padding: 30px;
        margin-top: 30px;
        color: #475569;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.01), 0 2px 4px rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(241, 245, 249, 0.8);
        position: relative;
    }
    
    .section-container h4 {
        font-size: 1.35rem;
        font-weight: 600;
        margin-bottom: 25px;
        color: #0f172a;
        letter-spacing: -0.02em;
        position: relative;
        padding-bottom: 12px;
    }
    
    .section-container h4:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 40px;
        height: 3px;
        background: linear-gradient(to right, #3b82f6, #2563eb);
    }
    
    /* Tabs */
    .dashboard-tabs {
        display: flex;
        margin-bottom: 25px;
        border-bottom: 1px solid #e2e8f0;
        position: relative;
    }
    
    .dashboard-tab {
        padding: 12px 20px;
        cursor: pointer;
        margin-right: 16px;
        font-weight: 500;
        transition: all 0.3s ease;
        color: #64748b;
        position: relative;
        overflow: hidden;
    }
    
    .dashboard-tab:before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(to right, #3b82f6, #2563eb);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s ease;
    }
    
    .dashboard-tab:hover {
        color: #3b82f6;
    }
    
    .dashboard-tab.active {
        color: #3b82f6;
        font-weight: 600;
    }
    
    .dashboard-tab.active:before {
        transform: scaleX(1);
    }
    
    .tab-content {
        display: none;
        opacity: 0;
        transition: all 0.5s ease;
        transform: translateY(10px);
    }
    
    .tab-content.active {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }
    
    /* Calendar Container */
    .calendar-container {
        height: 650px;
        margin: 20px 0 30px;
        position: relative;
    }
    
    #calendar-loading {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        background: rgba(255, 255, 255, 0.8);
        z-index: 10;
        font-size: 1.2rem;
        color: #3b82f6;
        display: none;
    }
    
    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid rgba(59, 130, 246, 0.2);
        border-left-color: #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Calendar Styles */
    .fc {
        font-family: 'Segoe UI', Arial, sans-serif;
    }
    
    .fc .fc-toolbar-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1e293b;
    }
    
    .fc .fc-button-primary {
        background: #ffffff;
        color: #475569;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        text-transform: capitalize;
        font-weight: 500;
    }
    
    .fc .fc-button-primary:not(:disabled):hover,
    .fc .fc-button-primary:not(:disabled):focus {
        background: #f8fafc;
        color: #3b82f6;
        border-color: #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background: #3b82f6;
        color: #fff;
        border-color: #2563eb;
    }
    
    .fc-theme-standard .fc-scrollgrid {
        border-color: #e2e8f0;
    }
    
    .fc .fc-daygrid-day-frame {
        min-height: 130px;
        padding: 6px;
    }
    
    .fc .fc-col-header-cell-cushion {
        padding: 10px 4px;
        color: #1e293b;
        font-weight: 600;
    }
    
    .fc .fc-daygrid-day-number {
        color: #64748b;
        font-size: 0.95rem;
        padding: 4px 8px;
    }
    
    .fc-theme-standard th {
        border-color: #e2e8f0;
        background: #f8fafc;
    }
    
    .fc-theme-standard td {
        border-color: #e2e8f0;
    }
    
    .fc .fc-event {
        padding: 4px 8px;
        font-size: 0.85rem;
        border-radius: 6px;
        margin-bottom: 2px;
        cursor: pointer;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        border: none;
    }
    
    .fc .fc-event-main {
        padding: 2px;
    }
    
    .fc .fc-event-time {
        font-weight: 600;
    }
    
    .fc .fc-event-title {
        font-weight: 500;
    }
    
    .fc .fc-timegrid-slot {
        height: 40px;
    }
    
    .fc .fc-timegrid-slot-label-cushion {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    .fc .fc-timegrid-now-indicator-line {
        border-color: #ef4444;
    }
    
    .fc .fc-timegrid-now-indicator-arrow {
        border-color: #ef4444;
        background: #ef4444;
    }
    
    /* Event Modal Styles */
    .event-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
    }
    
    .event-modal-content {
        background-color: #fff;
        margin: auto;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        width: 90%;
        max-width: 500px;
        position: relative;
        animation: modalFadeIn 0.3s ease;
    }
    
    @keyframes modalFadeIn {
        from {opacity: 0; transform: translateY(-20px);}
        to {opacity: 1; transform: translateY(0);}
    }
    
    .event-modal-close {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 1.5rem;
        cursor: pointer;
        color: #64748b;
        transition: color 0.2s;
    }
    
    .event-modal-close:hover {
        color: #ef4444;
    }
    
    .event-modal h3 {
        font-size: 1.35rem;
        font-weight: 600;
        margin-bottom: 20px;
        color: #0f172a;
        padding-bottom: 15px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .event-details p {
        margin-bottom: 12px;
        color: #475569;
        display: flex;
        align-items: center;
    }
    
    .event-details i {
        margin-right: 10px;
        color: #3b82f6;
        width: 20px;
    }
    
    /* Calendar event tooltip */
    .event-tooltip {
        padding: 5px;
    }
    
    .event-tooltip h4 {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .event-tooltip p {
        margin: 5px 0;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
    }
    
    .event-tooltip i {
        margin-right: 6px;
        width: 14px;
    }
    
    .status-pill {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .status-confirmed {
        background-color: #dcfce7;
        color: #16a34a;
    }
    
    .status-pending {
        background-color: #fef3c7;
        color: #d97706;
    }
    
    .status-canceled {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .modal-actions {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
        text-align: right;
    }

    .btn-view-details {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(37, 99, 235, 0.2);
    }

    .btn-view-details:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
    }

    /* Make calendar more glamorous */
    .fc-theme-standard td.fc-day-today {
        background: rgba(59, 130, 246, 0.05);
    }

    .fc .fc-timegrid-now-indicator-line {
        border-color: #3b82f6;
        border-width: 2px;
    }

    .fc .fc-timegrid-now-indicator-arrow {
        border-color: #3b82f6;
        border-width: 5px 5px 0 0;
        background: #3b82f6;
    }

    /* Improve tab styles */
    .dashboard-tab {
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    /* Empty schedule message */
    .no-bookings-calendar {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
        font-size: 1.1rem;
    }

    .no-bookings-calendar i {
        font-size: 3rem;
        margin-bottom: 15px;
        display: block;
        color: #cbd5e1;
    }

    .no-bookings-calendar a {
        display: inline-block;
        margin-top: 15px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
    }

    .no-bookings-calendar a:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
    }

    /* Responsive styles */
    @media (max-width: 992px) {
        .col-md-3 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        
        .dashboard-summary {
            gap: 15px;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-wrapper {
            padding: 20px;
        }
        
        .section-container {
            padding: 20px;
        }
        
        .calendar-container {
            height: 500px;
        }
        
        .feature-card {
            padding: 20px;
        }
        
        .summary-card {
            padding: 15px;
            min-width: 150px;
        }
        
        .summary-card .count {
            font-size: 2rem;
        }
        
        .dashboard-tab {
            padding: 10px 15px;
            font-size: 0.95rem;
        }
    }
    
    @media (max-width: 576px) {
        .col-md-3 {
            flex: 0 0 100%;
            max-width: 100%;
        }
        
        .dashboard-summary {
            flex-direction: column;
        }
        
        .summary-card {
            width: 100%;
        }
    }

    /* Glamorous Calendar Styles */
.fc {
    font-family: 'Segoe UI', Arial, sans-serif;
    --fc-border-color: rgba(226, 232, 240, 0.8);
    --fc-event-border-color: transparent;
    --fc-event-bg-color: #4ade80;
    --fc-event-text-color: white;
    --fc-today-bg-color: rgba(59, 130, 246, 0.05);
}

.fc .fc-toolbar-title {
    background-clip: text;
    font-size: 1.65rem;
    font-weight: 700;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    padding: 5px 0;
}

.fc .fc-button-primary {
    background: white;
    color: #475569;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border-radius: 8px;
    font-weight: 600;
    padding: 8px 16px;
    transition: all 0.2s ease;
}

.fc .fc-button-primary:not(:disabled):hover {
    background: #f1f5f9;
    color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.fc .fc-button-primary:not(:disabled).fc-button-active {
    background: #3b82f6;
    color: white;
    border-color: #2563eb;
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.2);
}

.fc-theme-standard td.fc-day-today {
    background: rgba(59, 130, 246, 0.05);
}

.fc-theme-standard th {
    background: #f8fafc;
    padding: 12px 0;
    font-weight: 600;
}

.fc .fc-timegrid-now-indicator-line {
    border-color: #3b82f6;
    border-width: 2px;
    box-shadow: 0 0 8px rgba(59, 130, 246, 0.6);
}

.fc .fc-timegrid-now-indicator-arrow {
    border-color: #3b82f6;
    background: #3b82f6;
    box-shadow: 0 0 8px rgba(59, 130, 246, 0.6);
}

.fc .fc-event {
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 0.9rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #16a34a;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.fc .fc-event:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

/* Enhancing event content */
.fc-event-title-container {
    padding: 3px 0;
}

.fc-event-time {
    font-weight: 700;
}

.calendar-container {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    border-radius: 12px;
    padding: 15px;
    background: white;
    height: 700px;
}

/* Enhanced Event Modal Styles */
.event-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(4px);
}

.event-modal-content {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    margin: auto;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05);
    width: 90%;
    max-width: 500px;
    position: relative;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
}

.event-modal-content.show {
    opacity: 1;
    transform: translateY(0);
}

.event-modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s ease;
}

.event-modal-close:hover {
    background: #fee2e2;
    color: #ef4444;
    transform: rotate(90deg);
}

.event-modal h3 {
    background-clip: text;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 25px;
    color: #0f172a;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.event-details {
    padding: 10px 0;
}

.event-details p {
    margin-bottom: 16px;
    color: #475569;
    display: flex;
    align-items: center;
    font-size: 1.05rem;
}

.event-details i {
    margin-right: 12px;
    color: #3b82f6;
    width: 20px;
    font-size: 1.1rem;
}

.modal-actions {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid rgba(226, 232, 240, 0.8);
    text-align: right;
}

.btn-view-details {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    position: relative;
    overflow: hidden;
    z-index: 1;
}

.btn-view-details:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.btn-view-details:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(37, 99, 235, 0.4);
}

.btn-view-details:hover:before {
    opacity: 1;
}

.btn-view-details:active {
    transform: translateY(-1px);
}

/* Color Legend Styles */
.color-legend-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    margin-top: 30px;
    border: 1px solid rgba(241, 245, 249, 0.8);
    transition: all 0.3s ease;
    position: relative;
}

.legend-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 15px;
    color: #1e293b;
    padding-bottom: 8px;
    border-bottom: 1px solid #f1f5f9;
}

.legend-section {
    margin-bottom: 15px;
}

.legend-section h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #475569;
    margin: 10px 0;
    display: flex;
    align-items: center;
}

.legend-section h4:before {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #3b82f6;
    margin-right: 8px;
}

.legend-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    color: #64748b;
}

.color-box {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 4px;
    margin-right: 8px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Legend toggle button for mobile */
.legend-toggle {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 100;
    padding: 10px 15px;
    border-radius: 30px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.legend-toggle i {
    margin-left: 5px;
}

.legend-toggle:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(37, 99, 235, 0.4);
}

/* Status badges with enhanced styling and consistent colors */
.status-badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.status-badge:before {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    margin-right: 5px;
}

.status-pending {
    background-color: #fef3c7;
    color: #d97706;
}

.status-pending:before {
    background-color: #d97706;
}

.status-confirmed, .status-completed {
    background-color: #dcfce7;
    color: #16a34a;
}

.status-confirmed:before, .status-completed:before {
    background-color: #16a34a;
}

.status-canceled {
    background-color: #fee2e2;
    color: #dc2626;
}

.status-canceled:before {
    background-color: #dc2626;
}

.status-in-progress {
    background-color: #dbeafe;
    color: #3b82f6;
}

.status-in-progress:before {
    background-color: #3b82f6;
}

/* Priority badges with enhanced styling */
.priority-badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    margin-right: 4px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.priority-badge:before {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    margin-right: 5px;
}

.priority-normal {
    background-color: #dbeafe;
    color: #3b82f6;
}

.priority-normal:before {
    background-color: #3b82f6;
}

.priority-urgent {
    background-color: #fef3c7;
    color: #d97706;
}

.priority-urgent:before {
    background-color: #d97706;
}

.priority-emergency {
    background-color: #fee2e2;
    color: #dc2626;
}

.priority-emergency:before {
    background-color: #dc2626;
}

/* Add colored border to service request items */
.service-request-item {
    border-left: 4px solid #3b82f6; /* Default blue border */
    padding-left: 12px;
}

.service-request-item.urgent {
    border-left-color: #d97706; /* Orange for urgent */
}

.service-request-item.emergency {
    border-left-color: #dc2626; /* Red for emergency */
}

/* Add colored indicators to booking items */
.booking-item {
    position: relative;
    border-left: 4px solid #3b82f6; /* Default color */
    padding-left: 12px;
}

.booking-item[data-facility="Swimming Pool"] {
    border-left-color: rgba(59, 130, 246, 1); /* Blue */
}

.booking-item[data-facility="Tennis Court"] {
    border-left-color: rgba(16, 185, 129, 1); /* Green */
}

.booking-item[data-facility="Basketball Court"] {
    border-left-color: rgba(249, 115, 22, 1); /* Orange */
}

.booking-item[data-facility="Function Hall"] {
    border-left-color: rgba(139, 92, 246, 1); /* Purple */
}

.booking-item[data-facility="Gym"] {
    border-left-color: rgba(236, 72, 153, 1); /* Pink */
}

/* Responsive styles for legend */
@media (max-width: 768px) {
    .color-legend-container {
        position: fixed;
        bottom: 15px;
        right: 15px;
        z-index: 990;
        max-width: 300px;
        max-height: 400px;
        overflow-y: auto;
        transform: translateY(calc(100% + 15px));
        transition: transform 0.3s ease-in-out;
        opacity: 0;
        margin-top: 0;
    }
    
    .color-legend-container.expanded {
        transform: translateY(0);
        opacity: 1;
    }
    
    .legend-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .legend-items {
        grid-template-columns: 1fr;
    }
}
</style>

</body>
</html>
<?php include './includes/footer.php';?>