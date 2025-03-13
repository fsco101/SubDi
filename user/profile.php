<?php

include '../includes/header.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch the user's profile data
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>User Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #fafafa;
            font-family: 'Arial', sans-serif;
        }

        /* Profile Container */
        .profile-container {
            max-width: 600px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        /* Profile Image */
        .profile-image img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e1306c;
            box-shadow: 0 4px 10px rgba(225, 48, 108, 0.4);
        }

        /* User Info */
        .profile-info h2 {
            margin-top: 10px;
            font-weight: bold;
            color: #333;
        }
        
        .profile-info p {
            color: #666;
            font-size: 14px;
        }

        /* Stats Section */
        .profile-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            padding: 10px;
            border-top: 1px solid #ddd;
        }

        .profile-stats div {
            text-align: center;
        }

        .profile-stats span {
            font-size: 18px;
            font-weight: bold;
            display: block;
            color: #333;
        }

        /* Edit Profile Button */
        .btn-edit {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background: #0095f6;
            color: white;
            font-weight: bold;
            border-radius: 6px;
            text-decoration: none;
            transition: 0.3s ease;
        }

        .btn-edit:hover {
            background: #0077cc;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                width: 90%;
            }
        }
    </style>
</head>
<body>

<div class="profile-container">
    <div class="profile-image">
        <img src="<?= !empty($user['image_url']) ? '/subdisystem/user/uploads/' . basename($user['image_url']) : '/subdisystem/user/uploads/default-profile.png'; ?>" 
             alt="Profile Picture">
    </div>
</div>


    <div class="profile-info">
        <h2><?= htmlspecialchars($user['f_name'] . ' ' . $user['l_name']); ?></h2>
        <p><?= htmlspecialchars($user['email']); ?></p>
        <p><?= htmlspecialchars($user['phone_number']); ?></p>
    </div>


    <a href="/subdisystem/user/edit_profile.php" class="btn-edit">Edit Profile</a>
</div>

</body>
</html>

<?php include '../includes/footer.php'; ?>
