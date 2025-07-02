<?php
session_start();

include("db_connect.php");
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];
    
    $username = mysqli_real_escape_string($conn, $username);
    
    $sql = "SELECT * FROM user_list WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row["password"])) {
            $_SESSION["user_id"] = $row["user_ID"];
            $_SESSION["username"] = $row["username"];
            $_SESSION["full_name"] = $row["full_name"];
            $_SESSION["role"] = $row["user_role"];
            if ($row["user_role"] == "admin") {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: cashier_dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Login</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo-container">
                <div class="logo">
                    <img src="logo/log.png" alt="">
                </div>
                <h1>St. Mark DrugStore</h1>
            </div>
            
            <div class="form-container">
                <h2>Staff Login</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" name="username" placeholder="Username" required>
                    </div>
                    
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    
                    <button type="submit" class="login-btn">Login</button>
                </form>
                    
            </div>
            
            <div class="footer">
                <p>&copy; 2023 St. Mark Drug Store. All rights reserved.</p>
            </div>
        </div>
        
        <div class="info-container">
            <div class="info-content">
                <h2>Welcome to <br> St. Mark Drug Store</h2>
                <p>Your trusted healthcare partner</p>
                <div class="features">
                    <div class="feature">
                        <i class="fas fa-pills"></i>
                        <span>Quality Medications</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-user-md"></i>
                        <span>Professional Staff</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-heartbeat"></i>
                        <span>Healthcare Solutions</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>