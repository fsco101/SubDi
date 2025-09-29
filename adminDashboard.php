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

// Fetch filters
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

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
                  WHERE b.booking_date LIKE ?
                  ORDER BY b.booking_date DESC";
$bookings_stmt = $conn->prepare($bookings_query);
$filter_date_param = "%$filter_date%";
$bookings_stmt->bind_param("s", $filter_date_param);
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
                  WHERE sr.created_at LIKE ?
                  ORDER BY sr.created_at DESC";
$service_stmt = $conn->prepare($service_query);
$service_stmt->bind_param("s", $filter_date_param);
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
                  WHERE r.rental_start LIKE ?
                  ORDER BY r.rental_start DESC";
$rentals_stmt = $conn->prepare($rentals_query);
$rentals_stmt->bind_param("s", $filter_date_param);
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
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css">
    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            </div>

            <div id="bookings-tab" class="tab-content active">
                <h4>All Bookings</h4>
                <form method="GET" class="filter-form">
                    <input type="date" name="filter_date" placeholder="Filter by date" value="<?= htmlspecialchars($filter_date); ?>">
                    <button type="submit">Filter</button>
                </form>
                <div class="grid-container">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="item-card booking-item">
                            <h5><i class="fas fa-building"></i> <?= htmlspecialchars($booking['facility_name']); ?></h5>
                            <p><i class="fas fa-user"></i> <?= htmlspecialchars($booking['user_name']); ?></p>
                            <p><i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($booking['booking_date'])); ?></p>
                            <p><i class="fas fa-clock"></i> <?= htmlspecialchars($booking['start_time']); ?> - <?= htmlspecialchars($booking['end_time']); ?></p>
                            <p><i class="fas fa-id-badge"></i> Booking ID: <?= htmlspecialchars($booking['booking_id']); ?></p>
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
                <form method="GET" class="filter-form">
                    <input type="date" name="filter_date" placeholder="Filter by date" value="<?= htmlspecialchars($filter_date); ?>">
                    <button type="submit">Filter</button>
                </form>
                <div class="grid-container">
                    <?php foreach ($service_requests as $request): ?>
                        <div class="item-card service-request-item">
                            <h5><i class="fas fa-tag"></i> <?= htmlspecialchars($request['category']); ?></h5>
                            <p><i class="fas fa-user"></i> <?= htmlspecialchars($request['user_name']); ?></p>
                            <p><i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($request['created_at'])); ?></p>
                            <p><i class="fas fa-signal"></i> Priority: <?= ucfirst(htmlspecialchars($request['priority'])); ?></p>
                            <p><i class="fas fa-id-badge"></i> Request ID: <?= htmlspecialchars($request['request_id']); ?></p>
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
                <form method="GET" class="filter-form">
                    <input type="date" name="filter_date" placeholder="Filter by date" value="<?= htmlspecialchars($filter_date); ?>">
                    <button type="submit">Filter</button>
                </form>
                <div class="grid-container">
                    <?php foreach ($rentals as $rental): ?>
                        <div class="item-card rental-item">
                            <h5><i class="fas fa-box"></i> <?= htmlspecialchars($rental['item_name']); ?></h5>
                            <p><i class="fas fa-user"></i> <?= htmlspecialchars($rental['user_name']); ?></p>
                            <p><i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($rental['rental_start'])); ?> to <?= date('F d, Y', strtotime($rental['rental_end'])); ?></p>
                            <p><i class="fas fa-id-badge"></i> Rental ID: <?= htmlspecialchars($rental['rental_id']); ?></p>
                            <p>
                                <span class="status status-<?= strtolower(htmlspecialchars($rental['status'])); ?>">
                                    <?= ucfirst(htmlspecialchars($rental['status'])); ?>
                                </span>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
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
        
            // Function to initialize charts
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
            
                // Fetch bookings data
                fetchChartData('bookings').then(data => {
                    if (data && data.length > 0) {
                        renderDoughnutChart('bookingsChart', data, 'facility_name', 'count', 'Booking Count by Facility', chartColors.blue, chartColors.borderBlue);
                    } else {
                        displayNoDataMessage('bookingsChart', 'No booking data available');
                    }
                });
            
                // Fetch service requests data
                fetchChartData('service_requests').then(data => {
                    if (data && data.length > 0) {
                        renderBarChart('serviceRequestsChart', data, 'category', 'count', 'Requests by Category', chartColors.purple, chartColors.borderPurple);
                    } else {
                        displayNoDataMessage('serviceRequestsChart', 'No service request data available');
                    }
                });
            
                // Fetch rentals data
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
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
            
                const ctx = canvas.getContext('2d');
                ctx.font = '14px "Segoe UI", Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#64748b';
                ctx.fillText(message, canvas.width / 2, canvas.height / 2);
            }
        
            // Add color legend after chart initialization
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
        
            // Add the color legend after charts are initialized
            setTimeout(addColorLegend, 500);
        });
    </script>
</body>
</html>

<style>
    :root {
    /* Dashboard-specific variables */
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

/* Override container for admin dashboard */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Dashboard specific components */
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

/* Tab navigation */
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

/* Content areas */
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

.filter-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.filter-form input, .filter-form button {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
}

.filter-form button {
    background-color: var(--primary-color);
    color: white;
    cursor: pointer;
}

.grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

/* Specialized item cards for dashboard */
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

/* Specialized item types */
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

/* Status indicators */
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
    background-color: var (--danger-color);
}

.status-completed {
    background-color: var(--info-color);
}

/* Responsive adjustments */
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

/* Charts Section Styles */
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
<?php include './includes/footer.php'; ?>