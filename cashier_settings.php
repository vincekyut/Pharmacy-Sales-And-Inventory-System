<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "cashier") {
    header("Location: index.php");
    exit();
}

$cashier_id = $_SESSION["user_id"];
$success_message = '';
$error_message = '';


$user_query = "SELECT * FROM user_list WHERE user_id = $cashier_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);


if (isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    $update_query = "UPDATE user_list SET 
                    full_name = '$full_name',
                    phone = '$phone'
                    WHERE user_id = $cashier_id";
    
    if (mysqli_query($conn, $update_query)) {
        $_SESSION["full_name"] = $full_name;
        $success_message = 'Profile updated successfully!';
        

        $user_result = mysqli_query($conn, $user_query);
        $user_data = mysqli_fetch_assoc($user_result);
    } else {
        $error_message = 'Error updating profile: ' . mysqli_error($conn);
    }
}


if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    

    $password_query = "SELECT password FROM users WHERE user_id = $cashier_id";
    $password_result = mysqli_query($conn, $password_query);
    $password_data = mysqli_fetch_assoc($password_result);
    
    if (password_verify($current_password, $password_data['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $password_update_query = "UPDATE users SET password = '$hashed_password' WHERE user_id = $cashier_id";
            
            if (mysqli_query($conn, $password_update_query)) {
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Error changing password: ' . mysqli_error($conn);
            }
        } else {
            $error_message = 'New passwords do not match!';
        }
    } else {
        $error_message = 'Current password is incorrect!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - St. Mark DrugStore Pharmacy</title>
    <link rel="stylesheet" href="css/cashier.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .settings-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        .settings-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .settings-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .settings-icon {
            width: 40px;
            height: 40px;
            background-color: #e6f7ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .settings-icon i {
            color: #0075fc;
            font-size: 20px;
        }
        .settings-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="tel"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
        }
        .submit-btn {
            padding: 10px 20px;
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        .submit-btn:hover {
            background-color: #0075fc;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #e6f7ee;
            color: #28a745;
            border: 1px solid #d4edda;
        }
        .alert-danger {
            background-color: #ffe6e6;
            color: #dc3545;
            border: 1px solid #f5c6cb;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background-color: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #adb5bd;
        }
        .profile-info h3 {
            font-size: 18px;
            margin: 0 0 5px 0;
        }
        .profile-info p {
            font-size: 14px;
            color: #666;
            margin: 0;
        }
        .tab-container {
            margin-bottom: 20px;
        }
        .tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .tab.active {
            color: #00b7ff;
            border-bottom-color: #00b7ff;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        @media (min-width: 768px) {
            .settings-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .dark-mode {
            background: #23272f !important;
            color: #e9ecef !important;
        }
        .dark-mode .settings-card, .dark-mode .main-content, .dark-mode .sidebar {
            background: #23272f !important;
            color: #e9ecef !important;
        }
        .dark-mode input, .dark-mode textarea, .dark-mode select {
            background: #2c313a !important;
            color: #e9ecef !important;
            border-color: #444 !important;
        }
        .compact-view .settings-card, .compact-view .main-content {
            padding: 10px !important;
        }
        .compact-view .form-group, .compact-view .checkbox-group {
            margin-bottom: 10px !important;
        }
        .large-text, .large-text * {
            font-size: 18px !important;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="logo-container">
                <div class="logo" id="logoContainer">
                    <img src="logo/log.png" alt="Logo" id="logoImage">
                </div>
                <button id="toggle-sidebar" class="toggle-sidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <ul class="menu">
                <hr>
                <br>
                <li data-title="Dashboard"><a href="cashier_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li data-title="New Sale"><a href="new_sale.php"><i class="fas fa-shopping-cart"></i> <span>New Sale</span></a></li>
                <li data-title="Sales History"><a href="sales_history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
                <li data-title="Product Search"><a href="product_search.php"><i class="fas fa-search"></i> <span>Product Search</span></a></li>
                <li class="active" data-title="Settings"><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            </ul>
            
            <div class="logout">
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> 
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <div class="main-content" id="main-content">
            <div class="header">
                <div class="page-title">
                    <h2>Settings</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <?php if(!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="settings-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo $user_data['full_name']; ?></h3>
                        <p><?php echo $user_data['username']; ?></p>
                    </div>
                </div>
                
                <div class="tab-container">
                    <div class="tabs">
                        <div class="tab active" data-tab="profile">Profile Settings</div>
                        <div class="tab" data-tab="password">Change Password</div>
                    </div>
                    <div id="profile" class="tab-content active">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo $user_data['full_name']; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo $user_data['phone']; ?>">
                            </div>
                            
                            <button type="submit" name="update_profile" class="submit-btn">Update Profile</button>
                        </form>
                    </div>
                    
                    <div id="password" class="tab-content">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="submit-btn">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toggleBtn = document.getElementById("toggle-sidebar");
            const sidebar = document.getElementById("sidebar");
            const mainContent = document.getElementById("main-content");
            
            const isCollapsed = localStorage.getItem("sidebarCollapsed") === "true";
            
            if (isCollapsed) {
                sidebar.classList.add("collapsed");
                mainContent.classList.add("expanded");
            }
            
            toggleBtn.addEventListener("click", function() {
                sidebar.classList.toggle("collapsed");
                mainContent.classList.toggle("expanded");
                
                localStorage.setItem("sidebarCollapsed", sidebar.classList.contains("collapsed"));
            });
            
            const menuItems = document.querySelectorAll('.menu li');
            menuItems.forEach(item => {
                const link = item.querySelector('a');
                const text = link.querySelector('span').textContent.trim();
                item.setAttribute('data-title', text);
            });
            
            // Tab functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    tab.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>