<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db_connect.php';


if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    exit();
}

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: inventory.php");
    exit();
}

$id = $_GET['id'];


$query = "SELECT * FROM product_list WHERE prod_ID = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: product_dashboard.php");
    exit();
}

$product = mysqli_fetch_assoc($result);


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = $_POST['product_name'];
    $cost_price = $_POST['cost_price'];
    $selling_price = $_POST['selling_price'];
    $prod_measure = $_POST['prod_measure'];
    $prod_qty = $_POST['prod_qty'];
    $prod_expiry = $_POST['prod_expiry'];
    
        $image_path = $product['image_path']; 
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                $target_dir = "uploads/products/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        

        $file_extension = pathinfo($_FILES["product_image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
                $check = getimagesize($_FILES["product_image"]["tmp_name"]);
        if ($check !== false) {
                        if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
            
                                if (!empty($product['image_path']) && file_exists($product['image_path'])) {
                    unlink($product['image_path']);
                }
            
                $image_path = $target_file;
            } else {
                $error_message = "Sorry, there was an error uploading your file. Error: " . error_get_last()['message'];
            }
        } else {
            $error_message = "File is not an image.";
        }
    } else if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] != 0 && $_FILES['product_image']['error'] != 4) {
                $upload_errors = array(
            1 => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
            2 => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
            3 => "The uploaded file was only partially uploaded",
            6 => "Missing a temporary folder",
            7 => "Failed to write file to disk",
            8 => "A PHP extension stopped the file upload"
        );
        $error_code = $_FILES['product_image']['error'];
        $error_message = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : "Unknown upload error";
    }
    
    if (!isset($error_message)) {
                $query = "UPDATE product_list SET 
                 product_name = ?, 
                 cost_price = ?, 
                 selling_price = ?, 
                 prod_measure = ?, 
                 prod_qty = ?, 
                 prod_expiry = ?, 
                 image_path = ? 
                 WHERE prod_ID = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
        } else {
            
            mysqli_stmt_bind_param($stmt, "sddsissi", $product_name, $cost_price, $selling_price, $prod_measure, $prod_qty, $prod_expiry, $image_path, $id);
            
            if (mysqli_stmt_execute($stmt)) {
                                $_SESSION['success_message'] = "Product updated successfully!";
                
                header("Location: product_dashboard.php");
                exit();
            } else {
                $error_message = "Error: " . mysqli_error($conn);
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
    <title>Edit Product - MediCare Pharmacy</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");

        :root {
            --primary-color: rgb(0, 183, 255);
            --secondary-color: #0075fc;
            --accent-color: #d8e9a8;
            --text-color: #333;
            --light-color: #f9f9f9;
            --dark-color: #191919;
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        body {
            background-color: var(--light-color);
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--secondary-color);
            color: white;
            padding: 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
            padding: 20px 10px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            padding: 10px 0;
            margin-bottom: 30px;
            position: relative;
        }

        .logo {
            width: 60px;
            height: 60px;
            background-color: transparent;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 10px;
            overflow: hidden;
            transition: opacity 0.3s ease, width 0.3s ease, height 0.3s ease;
        }

        .sidebar.collapsed .logo {
            opacity: 0;
            width: 0;
            height: 0;
            margin-right: 0;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar h1 {
            color: white;
            font-size: 20px;
            font-weight: 600;
            transition: opacity 0.3s ease, width 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed h1 {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .menu {
            list-style: none;
            margin-top: 20px;
        }

        .menu li {
            margin-bottom: 5px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .menu li.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .menu li:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .menu li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .menu li a i {
            margin-right: 10px;
            font-size: 18px;
            transition: margin 0.3s ease;
            min-width: 20px;
            text-align: center;
        }

        .sidebar.collapsed .menu li a i {
            margin-right: 0;
        }

        .menu li a span {
            transition: opacity 0.3s ease, width 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed .menu li a span {
            opacity: 0;
            width: 0;
            display: none;
        }

        .sidebar.collapsed .menu li a {
            justify-content: center;
            padding: 12px;
        }

        .logout {
            margin-top: auto;
            padding: 15px 0;
        }

        .logout a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .logout a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .logout a i {
            margin-right: 10px;
            font-size: 18px;
            transition: margin 0.3s ease;
            min-width: 20px;
            text-align: center;
        }

        .sidebar.collapsed .logout a i {
            margin-right: 0;
        }

        .logout a span {
            transition: opacity 0.3s ease, width 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed .logout a span {
            opacity: 0;
            width: 0;
            display: none;
        }

        .sidebar.collapsed .logout a {
            justify-content: center;
            padding: 12px;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s ease;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        .toggle-sidebar {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.3s ease;
        }

        .sidebar.collapsed .toggle-sidebar {
            right: 10px;
            left: auto;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .toggle-sidebar:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .toggle-sidebar i {
            transition: transform 0.3s ease;
        }

        .sidebar.collapsed .toggle-sidebar i {
            transform: rotate(180deg);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .page-title h2 {
            color: var(--text-color);
            font-size: 24px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info span {
            margin-right: 15px;
            font-weight: 500;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 20px;
        }

        .form-container {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .form-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: var(--text-color);
            font-size: 18px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 183, 255, 0.2);
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
        }

        .btn-secondary {
            background-color: #ddd;
            color: var(--text-color);
        }

        .btn-secondary:hover {
            background-color: #ccc;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .image-preview {
            width: 150px;
            height: 150px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 10px;
            overflow: hidden;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .image-preview-placeholder {
            color: #aaa;
            font-size: 14px;
            text-align: center;
        }

                .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 14px;
            overflow-x: auto;
        }

                .sidebar.collapsed .menu li {
            position: relative;
        }

        .sidebar.collapsed .menu li:hover::after {
            content: attr(data-title);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            margin-left: 10px;
        }

        .sidebar.collapsed .logout:hover::after {
            content: "Logout";
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: var(--sidebar-collapsed-width);
                padding: 20px 10px;
            }
            
            .sidebar h1,
            .menu li a span,
            .logout a span {
                opacity: 0;
                width: 0;
                display: none;
            }
            
            .menu li a,
            .logout a {
                justify-content: center;
                padding: 12px;
            }
            
            .menu li a i,
            .logout a i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .main-content {
                margin-left: var(--sidebar-collapsed-width);
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }
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
              <li data-title="Dashboard"><a href="#"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
              <li data-title="Users"><a href="#"><i class="fas fa-users"></i> <span>Users</span></a></li>
              <li data-title="Inventory"><a href="inventory.php"><i class="fas fa-boxes"></i> <span>Inventory</span></a></li>
              <li data-title="Sales"><a href="admin_newsale.php"><i class="fas fa-shopping-cart"></i> <span>New Sales</span></a></li>
              <li data-title="Product"><a href="product_category.php"><i class="fas fa-tags"></i> <span>Product Category</span></a></li>
              <li class="active" data-title="Product"><a href="product_dashboard.php"><i class="fas fa-pills"></i> <span>Product</span></a></li>
              <li data-title="Receive"><a href="#"><i class="fas fa-truck-loading"></i> <span>Receive Product</span></a></li>
              <li data-title="Suppliers"><a href="#"><i class="fas fa-truck"></i> <span>Suppliers</span></a></li>
              <li data-title="Settings"><a href="#"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
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
                    <h2>Edit Product</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <?php if(isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            
            <div class="form-container">
                <h3 class="form-title">Product Information</h3>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="product_name">Product Name *</label>
                                <input type="text" id="product_name" name="product_name" class="form-control" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="prod_measure">Measure/Unit *</label>
                                <input type="text" id="prod_measure" name="prod_measure" class="form-control" value="<?php echo htmlspecialchars($product['prod_measure']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="cost_price">Cost Price *</label>
                                <input type="number" id="cost_price" name="cost_price" class="form-control" step="0.01" min="0" value="<?php echo $product['cost_price']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="selling_price">Selling Price *</label>
                                <input type="number" id="selling_price" name="selling_price" class="form-control" step="0.01" min="0" value="<?php echo $product['selling_price']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="prod_qty">Quantity *</label>
                                <input type="number" id="prod_qty" name="prod_qty" class="form-control" min="0" value="<?php echo $product['prod_qty']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="prod_expiry">Expiry Date *</label>
                                <input type="date" id="prod_expiry" name="prod_expiry" class="form-control" value="<?php echo $product['prod_expiry']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_image">Product Image</label>
                        <input type="file" id="product_image" name="product_image" class="form-control" accept="image/*">
                        <div class="image-preview" id="imagePreview">
                            <?php if(!empty($product['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>?v=<?php echo time(); ?>" alt="Product Image">
                            <?php else: ?>
                                <div class="image-preview-placeholder">
                                    <p>No Image Available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="product_dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
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
            

            const imageInput = document.getElementById('product_image');
            const imagePreview = document.getElementById('imagePreview');
            
            imageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>
</html>