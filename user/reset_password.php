<?php
// Start session
session_start();
include '../includes/config.php';

// Initialize variables
$error = "";
$success = "";
$email = ""; // Ensure email is always initialized

// Validate token and email
if (!isset($_GET['token']) || !isset($_GET['email'])) {

} else {
    $token = $_GET['token'];
    $email = $_GET['email'];

    // Check if token exists and is valid
    $sql = "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expiry > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error = "Invalid or expired reset link.";
    }
}

// Preserve email from POST when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['email'])) {
        $email = $_POST['email'];
    }
    
    $new_password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Both password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password in users table (Changed column name from `password` to `password_hash`)
        $update_sql = "UPDATE users SET password_hash = ? WHERE email = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $hashed_password, $email);

        if ($update_stmt->execute()) {
            // Delete the reset request after successful reset
            $delete_sql = "DELETE FROM password_resets WHERE email = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();

            $success = "Your password has been reset successfully! <br><br>You can now <a href='/subdisystem/user/login.php' class='alert-link'>log in with your new password</a>.";
        } else {
            $error = "An error occurred while updating your password. Please try again or contact support.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .reset-form {
            max-width: 450px;
            margin: 60px auto;
            background: #fff;
            padding: 35px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background-color: #375ad3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .alert {
            border-radius: 4px;
        }
        .success-actions {
            margin-top: 20px;
            text-align: center;
        }
        .success-actions .btn {
            margin: 0 5px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="reset-form">
                    <h2 class="text-center mb-4">Reset Password</h2>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                        <div class="success-actions">
                            <a href="/subdisystem/user/login.php" class="btn btn-primary">Go to Login</a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            <div class="form-group">
                                <label for="password">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Auto dismiss alerts after 5 seconds (only for error messages)
        $(document).ready(function() {
            setTimeout(function() {
                $(".alert-dismissible").alert('close');
            }, 5000);
        });
    </script>
</body>
</html>