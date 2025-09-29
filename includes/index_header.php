<?php
session_start();
if (file_exists(__DIR__ . "/config.php")) {
    include __DIR__ . "/config.php";
} elseif (file_exists(__DIR__ . "/../config.php")) {
    include __DIR__ . "/../config.php";
} else {
    die("Error: config.php not found!");
}

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
            <a class="navbar-brand d-flex align-items-center" href="/subdisystem/index.php">
                <img src="/subdisystem/dashboard image/logo.gif" alt="Logo" class="navbar-logo me-2">
                <span class="d-none d-sm-inline">Subdivision Management</span>
                <span class="d-inline d-sm-none">SubM</span>
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Nav Items Container (empty but maintained for proper structure) -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <!-- Navigation items would go here -->
                </ul>
            </div>

            <!-- User Actions Area -->
            <div class="d-flex align-items-center">
                <!-- About Button -->
                <a href="/subdisystem/about.php" class="btn btn-secondary me-2 rounded-pill px-3">
                    <i class="bi bi-info-circle me-1"></i> About
                </a>
                
                <!-- Login Button -->
                <a href="/subdisystem/user/login.php" class="btn btn-outline-light me-2 rounded-pill px-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                </a>
                
                <!-- Sign Up Button -->
                <a href="/subdisystem/user/signup.php" class="btn btn-light rounded-pill px-3">
                    <i class="bi bi-person-plus me-1"></i> Sign Up
                </a>
            </div>
        </div>
    </nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Set active navigation item based on current page
        const currentLocation = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const linkPath = link.getAttribute('href');
            if (linkPath && currentLocation.includes(linkPath) && linkPath !== '/subdisystem/index.php') {
                link.classList.add('active');
                link.style.backgroundColor = 'rgba(255, 255, 255, 0.15)';
            } else if (currentLocation === '/subdisystem/index.php' && linkPath === '/subdisystem/index.php') {
                link.classList.add('active');
                link.style.backgroundColor = 'rgba(255, 255, 255, 0.15)';
            }
        });
    });
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
    
    .btn-outline-light {
        border-width: 2px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-outline-light:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .btn-light {
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-light:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        background-color: #f8f9fa;
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
        
        .d-flex.align-items-center {
            margin-top: 15px;
            width: 100%;
            justify-content: space-between;
        }
        
        .btn {
            flex: 1;
            text-align: center;
        }
    }
</style>
