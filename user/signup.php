<?php
include '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $f_name = trim(htmlspecialchars($_POST['f_name']));
    $l_name = trim(htmlspecialchars($_POST['l_name']));
    $email = trim(htmlspecialchars($_POST['email']));
    $password = $_POST['password'];
    $phone_number = trim(htmlspecialchars($_POST['phone_number']));
    $role = $_POST['role'];
    
    $error = "";
    $success = "";

    // Validate Password Strength
    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $error = "Password must be at least 8 characters, include 1 uppercase, 1 lowercase, 1 number, and 1 special character.";
    }

    if (empty($error)) {
        // Check if the email already exists
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "This email is already registered. Please login or use a different email.";
        } else {
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $image_url = "/subdisystem/user/uploads/default_profile.jpg"; // Default profile image

            // Handle Secure Profile Picture Upload
            if (isset($_FILES['uploads']) && $_FILES['uploads']['error'] == 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                $fileType = mime_content_type($_FILES['uploads']['tmp_name']);
                $maxSize = 2 * 1024 * 1024; // 2MB limit

                if (!in_array($fileType, $allowedTypes)) {
                    $error = "Invalid file type. Only JPG and PNG are allowed.";
                } elseif ($_FILES['uploads']['size'] > $maxSize) {
                    $error = "File too large. Maximum size is 2MB.";
                } else {
                    $uploadDir = '../user/uploads/';
                    $fileName = uniqid() . "_" . basename($_FILES['uploads']['name']);
                    $targetFile = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['uploads']['tmp_name'], $targetFile)) {
                        $image_url = '/subdisystem/user/uploads/' . $fileName;
                    } else {
                        $error = "Error uploading file.";
                    }
                }
            }

            // If no errors, insert into database
            if (empty($error)) {
                $query = "INSERT INTO users (f_name, l_name, email, password_hash, phone_number, role, image_url) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssss", $f_name, $l_name, $email, $password_hash, $phone_number, $role, $image_url);

                if ($stmt->execute()) {
                    $success = "You have successfully signed up. <a href='login.php' class='text-info'>Login here</a>.";
                } else {
                    $error = "Error registering user. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Subdivision Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-image: url('assets/img/subdivision-bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            margin: 0;
            position: relative;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(6px);
            z-index: -1;
        }
        
        .signup-card {
            width: 100%;
            max-width: 520px;
            background: rgba(30, 30, 30, 0.85);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            position: relative;
            margin: 20px auto;
        }
        
        .signup-header {
            background: rgba(23, 162, 184, 0.9);
            padding: 25px 30px;
            text-align: center;
            color: white;
        }
        
        .signup-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .signup-body {
            padding: 30px;
            color: #e1e1e1;
        }
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 15px;
            height: auto;
            border-radius: 8px;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #17a2b8;
            box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.25);
            color: white;
        }
        
        .form-control::placeholder, .form-select::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
        }
        
        .form-select option {
            background-color: #333;
            color: white;
        }
        
        .btn-signup {
            background: #17a2b8;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-signup:hover {
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
        
        .success-message {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .form-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 15px;
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
            color: rgba(255, 255, 255, 0.6);
            font-size: 1.1rem;
            z-index: 10;
        }
        
        .icon-input {
            padding-left: 45px;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #17a2b8;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: white;
            text-decoration: underline;
        }
        
        .form-text {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
            margin-top: -12px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="signup-card">
    <div class="signup-header">
        <h2><i class="bi bi-person-plus"></i> Subdivision Sign Up</h2>
    </div>
    
    <div class="signup-body">
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-circle"></i> <?= $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="bi bi-check-circle"></i> <?= $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <label for="f_name" class="form-label">First Name</label>
                        <div class="position-relative">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="f_name" id="f_name" class="form-control icon-input" required placeholder="Enter first name">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <label for="l_name" class="form-label">Last Name</label>
                        <div class="position-relative">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="l_name" id="l_name" class="form-control icon-input" required placeholder="Enter last name">
                        </div>
                    </div>
                </div>
            </div>

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
                    <input type="password" name="password" id="password" class="form-control icon-input" required placeholder="Create a password">
                </div>
                <div class="form-text">
                    Password must have at least 8 characters, 1 uppercase, 1 lowercase, 1 number, and 1 special character.
                </div>
            </div>

            <div class="input-group">
                <label for="phone_number" class="form-label">Phone Number</label>
                <div class="position-relative">
                    <i class="bi bi-telephone input-icon"></i>
                    <input type="text" name="phone_number" id="phone_number" class="form-control icon-input" placeholder="Enter phone number">
                </div>
            </div>

            <div class="input-group">
                <label for="role" class="form-label">Role</label>
                <div class="position-relative">
                    <i class="bi bi-person-badge input-icon"></i>
                    <select name="role" id="role" class="form-select icon-input" required>
                        <option value="" disabled selected>Select your role</option>
                        <option value="resident">Resident</option>
                        <option value="admin">Admin</option>
                        <option value="outsider">Outsider</option>
                    </select>
                </div>
            </div>

            <div class="input-group">
                <label for="uploads" class="form-label">Profile Picture</label>
                <div class="position-relative">
                    <i class="bi bi-image input-icon"></i>
                    <input type="file" name="uploads" id="uploads" class="form-control icon-input" accept="image/*">
                </div>
                <div class="form-text">
                    Only JPG and PNG files are allowed (Max size: 2MB)
                </div>
            </div>

            <button type="submit" class="btn btn-signup">
                <i class="bi bi-check-circle"></i> Create Account
            </button>
        </form>

        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to Login
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>