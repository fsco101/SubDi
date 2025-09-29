<?php
include './includes/index_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subdivision Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        .video-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .video-container video {
            min-width: 100%;
            min-height: 100%;
            object-fit: cover;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.8;
        }

        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .content {
            position: relative;
            z-index: 2;
            color: white;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 20px;
        }

        .content h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            letter-spacing: 1px;
            font-weight: 700;
            text-transform: uppercase;
            animation: fadeInDown 1.5s;
        }

        .content p {
            font-size: 1.2rem;
            max-width: 700px;
            margin-bottom: 30px;
            line-height: 1.6;
            animation: fadeIn 2s;
        }

        .btn {
            padding: 12px 30px;
            background-color: #3366cc;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            animation: fadeInUp 2s;
        }

        .btn:hover {
            background-color: #254b99;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .nav-button {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid white;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .nav-button:hover {
            background-color: white;
            color: #333;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .content h1 {
                font-size: 2.5rem;
            }
            .content p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="video-container">
        <div class="video-overlay"></div>
        <video autoplay loop muted>
            <source src="dashboard image/background.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>

    <div class="content">
        <h1>Subdivision Management System</h1>
        <p>
            Welcome to our comprehensive Subdivision Management System, designed to streamline community operations and enhance residents' living experience. Our platform provides efficient tools for property management, amenity scheduling, maintenance requests, and resident communications, all in one integrated solution.
        </p>
        <a href="/subdisystem/user/login.php" class="btn">Get Started</a>
    </div>

    <?php include './includes/footer.php'; ?>
</body>
</html>
