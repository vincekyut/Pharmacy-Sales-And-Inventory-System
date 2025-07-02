<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_start();
include 'db_connect.php';
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'product_name';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';
$allowed_columns = ['product_id', 'product_name', 'unit_price', 'selling_price', 'prod_measure', 'quantity', 'prod_expiry', 'category_id'];
if (!in_array($sort_by, $allowed_columns)) {
    $sort_by = 'product_name';
}
if ($sort_order != 'ASC' && $sort_order != 'DESC') {
    $sort_order = 'ASC';
}
if (!empty($search)) {
    $query = "SELECT p.*, c.category_name, 
              (SELECT COUNT(*) FROM product_batches WHERE product_id = p.product_id AND quantity > 0) as active_batch_count,
              (SELECT SUM(quantity) FROM inventory WHERE product_id = p.product_id) as inventory_quantity
              FROM product_list p 
              LEFT JOIN category_list c ON p.category_id = c.category_id 
              WHERE p.product_name LIKE ? ORDER BY $sort_by $sort_order";
    $stmt = mysqli_prepare($conn, $query);
    $search_param = "%" . $search . "%";
    mysqli_stmt_bind_param($stmt, "s", $search_param);
} else {
    $query = "SELECT p.*, c.category_name, 
              (SELECT COUNT(*) FROM product_batches WHERE product_id = p.product_id AND quantity > 0) as active_batch_count,
              (SELECT SUM(quantity) FROM inventory WHERE product_id = p.product_id) as inventory_quantity
              FROM product_list p 
              LEFT JOIN category_list c ON p.category_id = c.category_id 
              ORDER BY $sort_by $sort_order";
    $stmt = mysqli_prepare($conn, $query);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - MediCare Pharmacy</title>
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
            gap: 15px;
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
        .search-form {
            display: flex;
            gap: 10px;
            width: 100%;
            max-width: 500px;
            margin-bottom: 20px;
        }
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .search-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .search-btn:hover {
            background-color: var(--secondary-color);
        }
        .product-table-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        .product-table {
            width: 100%;
            border-collapse: collapse;
        }
        .product-table th,
        .product-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .product-table th a {
            text-decoration: none;
        }
        .product-table th {
            font-weight: 600;
            color: #777;
            background-color: #f9f9f9;
            cursor: pointer;
        }
        .product-table th:hover {
            background-color: #f0f0f0;
        }
        .product-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .sort-icon {
            margin-left: 5px;
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
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .btn-edit, .btn-delete, .btn-batches {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-batches {
            background-color: #17a2b8;
            color: white;
        }
        .btn-edit:hover {
            background-color: #e0a800;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .btn-batches:hover {
            background-color: #138496;
        }
        .add-product-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .add-product-btn:hover {
            background-color: var(--secondary-color);
        }
        .batch-indicator {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 5px;
        }
        .batch-count {
            background-color: #e3f2fd;
            color: #0d47a1;
        }
        .expiry-warning {
            color: #dc3545;
            font-weight: 500;
        }
        .expiry-ok {
            color: #28a745;
        }
        .low-stock {
            color: #dc3545;
            font-weight: 500;
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
            .search-form {
                max-width: 100%;
            }
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .modal-title {
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .submit-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .submit-btn:hover {
            background-color: var(--secondary-color);
        }
        .batch-section {
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .batch-title {
            font-weight: 500;
            margin-bottom: 10px;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
              <li data-title="Dashboard"><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
              <li data-title="Users"><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
              <li data-title="Inventory"><a href="inventory.php"><i class="fas fa-boxes"></i> <span>Inventory</span></a></li>
              <li data-title="Sales"><a href="admin_newsale.php"><i class="fas fa-shopping-cart"></i> <span>New Sales</span></a></li>
              <li data-title="Sales History"><a href="admin_sales-history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
              <li data-title="Product"><a href="product_category.php"><i class="fas fa-tags"></i> <span>Product Category</span></a></li>
              <li class="active" data-title="Product"><a href="product_dashboard.php"><i class="fas fa-pills"></i> <span>Product</span></a></li>
              <li data-title="Receive"><a href="receive_product.php"><i class="fas fa-truck-loading"></i> <span>Receive Product</span></a></li>
              <li data-title="Batches"><a href="batch_management.php"><i class="fas fa-layer-group"></i> <span>Batch Management</span></a></li>
              <li data-title="Suppliers"><a href="suppliers.php"><i class="fas fa-truck"></i> <span>Suppliers</span></a></li>
              <li data-title="Settings"><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
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
                    <h2>Product List</h2>
                </div>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
                ?>
            </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
                ?>
            </div>
            <?php endif; ?>
            <div class="header-actions">
                <form action="" method="GET" class="search-form">
                    <input type="text" name="search" class="search-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                </form>
                <button class="add-product-btn" id="addProductBtn">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </div>
            <div class="product-table-container">
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>
                                <a href="?sort=product_id&order=<?php echo $sort_by == 'product_id' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    ID
                                    <?php if($sort_by == 'product_id'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=product_name&order=<?php echo $sort_by == 'product_name' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Product Name
                                    <?php if($sort_by == 'product_name'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=unit_price&order=<?php echo $sort_by == 'unit_price' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Unit Price
                                    <?php if($sort_by == 'unit_price'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=selling_price&order=<?php echo $sort_by == 'selling_price' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Selling Price
                                    <?php if($sort_by == 'selling_price'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=prod_measure&order=<?php echo $sort_by == 'prod_measure' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Measure
                                    <?php if($sort_by == 'prod_measure'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Quantity</th>
                            <th>
                                <a href="?sort=prod_expiry&order=<?php echo $sort_by == 'prod_expiry' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Expiry Date
                                    <?php if($sort_by == 'prod_expiry'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=category_id&order=<?php echo $sort_by == 'category_id' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Category
                                    <?php if($sort_by == 'category_id'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if(mysqli_num_rows($result) > 0):
                            while($row = mysqli_fetch_assoc($result)):
                                $batch_query = "SELECT * FROM product_batches 
                                               WHERE product_id = ? AND quantity > 0 
                                               ORDER BY expiry_date ASC LIMIT 1";
                                $batch_stmt = mysqli_prepare($conn, $batch_query);
                                mysqli_stmt_bind_param($batch_stmt, "i", $row['product_id']);
                                mysqli_stmt_execute($batch_stmt);
                                $batch_result = mysqli_stmt_get_result($batch_stmt);
                                $active_batch = mysqli_fetch_assoc($batch_result);
                                $expiry_date = $active_batch ? $active_batch['expiry_date'] : $row['prod_expiry'];
                                $unit_price = $active_batch ? $active_batch['unit_price'] : $row['unit_price'];
                                $selling_price = $active_batch ? $active_batch['selling_price'] : $row['selling_price'];
                                $today = new DateTime();
                                $expiry = new DateTime($expiry_date);
                                $interval = $today->diff($expiry);
                                $days_until_expiry = $interval->days;
                                $is_expired = $today > $expiry;
                                $inventory_quantity = $row['inventory_quantity'] ?? 0;
                                $low_stock = $inventory_quantity < 10;
                        ?>
                        <tr>
                            <td>
                                <?php if(!empty($row['image_path']) && file_exists($row['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['image_path']); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($row['product_name']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-image" style="background-color: #f0f0f0;"></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $row['product_id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['product_name']); ?>
                                <span class="batch-indicator batch-count"><?php echo $row['active_batch_count']; ?> Batches</span>
                            </td>
                            <td>₱<?php echo number_format($unit_price, 2); ?></td>
                            <td>₱<?php echo number_format($selling_price, 2); ?></td>
                            <td><?php echo htmlspecialchars($row['prod_measure']); ?></td>
                            <td class="<?php echo $low_stock ? 'low-stock' : ''; ?>">
                                <?php echo $inventory_quantity; ?>
                                <?php if($low_stock): ?>
                                    <i class="fas fa-exclamation-triangle" title="Low Stock"></i>
                                <?php endif; ?>
                            </td>
                            <td class="<?php echo $is_expired ? 'expiry-warning' : 'expiry-ok'; ?>">
                                <?php echo date('Y-m-d', strtotime($expiry_date)); ?>
                                <?php if($is_expired): ?>
                                    <i class="fas fa-exclamation-circle" title="Expired"></i>
                                <?php elseif($days_until_expiry < 30): ?>
                                    <i class="fas fa-clock" title="Expiring soon"></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-edit" onclick="editProduct(<?php echo $row['product_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="deleteProduct(<?php echo $row['product_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">No products found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 class="modal-title">Add New Product</h3>
            <form id="addProductForm" action="add_product.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php
                            $cat_query = "SELECT * FROM category_list ORDER BY category_name";
                            $cat_result = mysqli_query($conn, $cat_query);
                            while($cat = mysqli_fetch_assoc($cat_result)) {
                                echo '<option value="'.$cat['category_id'].'">'.$cat['category_name'].'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="prod_measure">Measure</label>
                        <input type="text" id="prod_measure" name="prod_measure" class="form-control" required>
                    </div>
                </div>
                <div class="batch-section">
                    <div class="batch-title">Initial Batch Details</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="unit_price">Unit Price</label>
                            <input type="number" id="unit_price" name="unit_price" step="0.01" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="selling_price">Selling Price</label>
                            <input type="number" id="selling_price" name="selling_price" step="0.01" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="prod_expiry">Expiry Date</label>
                            <input type="date" id="prod_expiry" name="prod_expiry" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="product_image">Product Image</label>
                    <input type="file" id="product_image" name="product_image" class="form-control">
                </div>
                <button type="submit" class="submit-btn">Add Product</button>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toggleBtn = document.getElementById("toggle-sidebar");
            const sidebar = document.getElementById("sidebar");
            const mainContent = document.getElementById("main-content");
            const modal = document.getElementById("addProductModal");
            const addBtn = document.getElementById("addProductBtn");
            const closeBtn = document.getElementsByClassName("close")[0];
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
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(function() {
                    alerts.forEach(alert => {
                        alert.style.transition = 'opacity 1s';
                        alert.style.opacity = '0';
                        setTimeout(function() {
                            alert.style.display = 'none';
                        }, 1000);
                    });
                }, 5000);
            }
            addBtn.onclick = function() {
                modal.style.display = "block";
            }
            closeBtn.onclick = function() {
                modal.style.display = "none";
            }
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        });
        function editProduct(productId) {
            window.location.href = 'edit_product.php?id=' + productId;
        }
        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                window.location.href = 'delete_product.php?id=' + productId;
            }
        }
    </script>
</body>
</html>