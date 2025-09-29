<?php
ob_start();

include '../includes/index_header.php';
$error = "";

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['email']) || !isset($_POST['password'])) {
        $error = "Email and password are required.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Fetch user data securely
        $query = "SELECT user_id, f_name, role, status, password_hash FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($query)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Check if account is active
                if ($user['status'] === 'inactive') {
                    $error = "Your account has been deactivated. Please contact the administrator.";
                } elseif (password_verify($password, $user['password_hash'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['f_name'] = $user['f_name'];
                    $_SESSION['role'] = $user['role'];

                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header("Location: /subdisystem/adminDashboard.php");
                    } else {
                        header("Location: /subdisystem/dashboard.php");
                    }
                    exit();
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "No account found with that email.";
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Subdivision Management System</title>
    <link rel="stylesheet" href="./subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
            position: relative;
            /* Removed overflow: hidden to allow footer to be shown */
        }
        
        #bg-video {
            position: fixed;
            top: 0;
            left: 0;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            z-index: -2;
            object-fit: cover;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }
        
        .main-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            z-index: 1;
            margin-bottom: 60px; /* Added margin for footer */
        }
        
        .welcome-text {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.7);
            max-width: 800px;
        }
        
        .welcome-text h1 {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .welcome-text p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(30, 30, 30, 0.85);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            position: relative;
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            background: rgba(23, 162, 184, 0.9);
            padding: 25px 30px;
            text-align: center;
            color: white;
        }
        
        .login-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .login-body {
            padding: 30px;
            color: #e1e1e1;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.7); /* Increase background opacity for better visibility */
            border: 1px solid rgba(0, 0, 0, 0.3); /* Darker border for better visibility */
            color: #000000; 
            padding: 12px 15px;
            height: auto;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.9); /* Even more visible when focused */
            border-color: #17a2b8;
            box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.25);
            color: #000000; /* Keep text black when focused */
        }
        
        .form-control::placeholder {
            color: #333333; /* Darker placeholder text for better visibility */
        }
        
        .btn-login {
            background: #17a2b8;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn-login:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .error-message {
            background: rgba(255, 82, 82, 0.2);
            color: #ff5252;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: rgba(255, 255, 255, 0.5);
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .divider::before {
            margin-right: 10px;
        }
        
        .divider::after {
            margin-left: 10px;
        }
        
        .links-section {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .links-section a {
            color: #17a2b8;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
            padding: 5px;
        }
        
        .links-section a:hover {
            color: white;
            text-decoration: underline;
        }
        
        .form-label {
            color:rgb(50, 48, 48); /* Change label color to white for better visibility against dark card */
            font-weight: 500; /* Make labels bolder */
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .position-relative {
            position: relative;
            width: 100%;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #333333; /* Darker icon color for better visibility */
            font-size: 1.1rem;
            z-index: 10;
        }
        
        .icon-input {
            padding-left: 45px;
        }
        
        @media (max-width: 576px) {
            .welcome-text h1 {
                font-size: 1.8rem;
            }
            
            .welcome-text p {
                font-size: 1rem;
            }
            
            .login-card {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<video id="bg-video" autoplay muted loop>
    <source src="/subdisystem/dashboard image/background.mp4" type="video/mp4">
    Your browser does not support the video tag.
</video>
<div class="overlay"></div>

<div class="main-container">
    <div class="welcome-text">
        <h1>Welcome to Subdivision Management System</h1>
        <p>Manage your subdivision efficiently and effectively.</p>
    </div>
    
    <div class="login-card">
        <div class="login-header">
            <h2><i class="bi bi-buildings"></i> Login</h2>
        </div>
        
        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-circle"></i> <?= $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="position-relative">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" name="email" id="email" class="form-control icon-input" required placeholder="Enter your email">
                    </div>
                </div>

                <div class="input-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="position-relative">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="form-control icon-input" required placeholder="Enter your password">
                        <i id="togglePassword" class="bi bi-eye-slash position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #000000;"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </button>
            </form>

            <div class="divider">or</div>
              
            <div class="links-section">
                <a href="/subdisystem/user/forgot_password.php">
                    <i class="bi bi-key"></i> Forgot Password?
                </a>
                <a href="/subdisystem/user/signup.php">
                    <i class="bi bi-person-plus"></i> Sign Up
                </a> <!-- Fixed missing closing a tag -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>