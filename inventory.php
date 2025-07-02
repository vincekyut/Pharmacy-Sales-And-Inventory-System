<?php
// Add this code right after the PHP opening tag to force no caching
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
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'inventory_id';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';


$allowed_columns = ['inventory_id', 'product_id', 'quantity', 'reorder_level', 'date_stocked', 'last_updated'];
if (!in_array($sort_by, $allowed_columns)) {
    $sort_by = 'inventory_id';
}


if ($sort_order != 'ASC' && $sort_order != 'DESC') {
    $sort_order = 'ASC';
}

if (!empty($search)) {
    $query = "SELECT i.*, p.product_name, p.image_path FROM inventory i 
              LEFT JOIN product_list p ON i.product_id = p.product_id 
              WHERE p.product_name LIKE ? ORDER BY $sort_by $sort_order";
    $stmt = mysqli_prepare($conn, $query);
    $search_param = "%" . $search . "%";
    mysqli_stmt_bind_param($stmt, "s", $search_param);
} else {
    $query = "SELECT i.*, p.product_name, p.image_path FROM inventory i 
              LEFT JOIN product_list p ON i.product_id = p.product_id 
              ORDER BY $sort_by $sort_order";
    $stmt = mysqli_prepare($conn, $query);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);


$count_query = "SELECT COUNT(*) as total FROM inventory";
$count_result = mysqli_query($conn, $count_query);
$count_data = mysqli_fetch_assoc($count_result);
$total_items = $count_data['total'];


global $conn;
$threshold_query = "SELECT low_stock_threshold FROM system_settings LIMIT 1";
$threshold_result = mysqli_query($conn, $threshold_query);
$threshold_row = mysqli_fetch_assoc($threshold_result);
$low_stock_threshold = $threshold_row['low_stock_threshold'];

$low_stock_query = "SELECT COUNT(*) as low_stock FROM inventory WHERE quantity < $low_stock_threshold";
$low_stock_result = mysqli_query($conn, $low_stock_query);
$low_stock_data = mysqli_fetch_assoc($low_stock_result);
$low_stock_count = $low_stock_data['low_stock'];


$today = date('Y-m-d');
$expired_query = "SELECT COUNT(*) as expired FROM inventory i 
                  JOIN product_list p ON i.product_id = p.product_id 
                  WHERE p.prod_expiry < '$today'";
$expired_result = mysqli_query($conn, $expired_query);
$expired_data = mysqli_fetch_assoc($expired_result);
$expired_count = $expired_data['expired'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - MediCare Pharmacy</title>
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

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background-color: rgba(0, 183, 255, 0.1);
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 24px;
        }

        .stat-info h3 {
            font-size: 14px;
            color: #777;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-color);
        }

        .product-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            width: 100%;
            max-width: 500px;
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

        .product-actions-cell {
            display: flex;
            gap: 10px;
        }

        .edit-btn, .view-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .edit-btn:hover, .view-btn:hover {
            background-color: var(--secondary-color);
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a {
            color: var(--text-color);
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 4px;
            transition: all 0.3s ease;
        }

        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
            border: 1px solid var(--primary-color);
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;
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

        .low-stock {
            color: #ff4d4d;
            font-weight: 600;
        }

        .expired {
            background-color: #ffeeee;
        }

        /* Tooltip for collapsed sidebar */
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

            .product-actions {
                flex-direction: column;
                gap: 10px;
            }

            .search-form {
                max-width: 100%;
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
              <li data-title="Dashboard"><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
              <li data-title="Users"><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
              <li class="active" data-title="Inventory"><a href="inventory.php"><i class="fas fa-boxes"></i> <span>Inventory</span></a></li>
              <li data-title="Sales"><a href="admin_newsale.php"><i class="fas fa-shopping-cart"></i> <span>New Sales</span></a></li>
              <li data-title="Sales History"><a href="admin_sales-history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
              <li data-title="Product"><a href="product_category.php"><i class="fas fa-tags"></i> <span>Product Category</span></a></li>
              <li data-title="Product"><a href="product_dashboard.php"><i class="fas fa-pills"></i> <span>Product</span></a></li>
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
                    <h2>Inventory Management</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Inventory Items</h3>
                        <p><?php echo $total_items; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #ff4d4d; background-color: rgba(255, 77, 77, 0.1);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Low Stock Items</h3>
                        <p><?php echo $low_stock_count; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #ff4d4d; background-color: rgba(255, 77, 77, 0.1);">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Expired Products</h3>
                        <p><?php echo $expired_count; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="product-actions">
                <form action="" method="GET" class="search-form">
                    <input type="text" name="search" class="search-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>
            
            <div class="product-table-container">
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>
                                <a href="?sort=inventory_id&order=<?php echo $sort_by == 'inventory_id' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Inventory ID
                                    <?php if($sort_by == 'inventory_id'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=product_id&order=<?php echo $sort_by == 'product_id' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Product ID
                                    <?php if($sort_by == 'product_id'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Product Name</th>
                            <th>
                                <a href="?sort=quantity&order=<?php echo $sort_by == 'quantity' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Quantity
                                    <?php if($sort_by == 'quantity'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=reorder_level&order=<?php echo $sort_by == 'reorder_level' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Reorder Level
                                    <?php if($sort_by == 'reorder_level'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=date_stocked&order=<?php echo $sort_by == 'date_stocked' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Date Stocked
                                    <?php if($sort_by == 'date_stocked'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=last_updated&order=<?php echo $sort_by == 'last_updated' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">
                                    Last Updated
                                    <?php if($sort_by == 'last_updated'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if(mysqli_num_rows($result) > 0):
                            while($row = mysqli_fetch_assoc($result)):
                                $threshold_query = "SELECT low_stock_threshold FROM system_settings LIMIT 1";
                                $threshold_result = mysqli_query($conn, $threshold_query);
                                $threshold_row = mysqli_fetch_assoc($threshold_result);
                                $row_low_stock_threshold = $threshold_row['low_stock_threshold'];

                                $is_low_stock = $row['quantity'] < $row_low_stock_threshold;
                        ?>
                        <tr>
                            <td>
                                <?php if(!empty($row['image_path']) && file_exists($row['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['image_path']); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($row['product_name']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-image" style="background-color: #f0f0f0;"></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $row['inventory_id']; ?></td>
                            <td><?php echo $row['product_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['product_name'] ?? 'N/A'); ?></td>
                            <td class="<?php echo $is_low_stock ? 'low-stock' : ''; ?>">
                                <?php echo $row['quantity']; ?>
                                <?php if($is_low_stock): ?>
                                    <i class="fas fa-exclamation-circle" title="Low Stock"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $threshold_query = "SELECT low_stock_threshold FROM system_settings LIMIT 1";
                                    $threshold_result = mysqli_query($conn, $threshold_query);
                                    $threshold_row = mysqli_fetch_assoc($threshold_result);
                                    $row_low_stock_threshold = $threshold_row['low_stock_threshold'];
                                    echo $row_low_stock_threshold;
                                ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($row['date_stocked'])); ?></td>
                            <td><?php echo $row['last_updated']; ?></td>

                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">No inventory items found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
        });
    </script>
</body>
</html>
